/**
 * End-to-end smoke test (Playwright). Requires WP_DEBUG + BMN_SMS_DRIVER=log so the OTP is echoed.
 * Usage: BASE=http://localhost:8088 OUT=./shots node tests/e2e/e2e.js
 */
const { chromium } = require( 'playwright' );
const fs = require( 'fs' );
const BASE = process.env.BASE || 'http://localhost:8088';
const OUT = process.env.OUT || 'shots';
fs.mkdirSync( OUT, { recursive: true } );

( async () => {
	const browser = await chromium.launch();
	const ctx = await browser.newContext( { viewport: { width: 1280, height: 860 }, geolocation: { latitude: 33.868, longitude: 57.429 }, permissions: [ 'geolocation' ] } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	page.on( 'console', ( m ) => { if ( m.type() === 'error' && ! /tile\.openstreetmap/.test( m.text() ) && ! /ERR_/.test( m.text() ) ) { errors.push( m.text() ); } } );
	const step = async ( name, fn ) => { process.stdout.write( '• ' + name + ' … ' ); await fn(); console.log( 'ok' ); };

	// Unique phone per run avoids the per-phone hourly limit.
	const phone = '0912' + String( Date.now() ).slice( -7 );
	let otp = '';
	page.on( 'response', async ( r ) => { if ( r.url().includes( '/auth/otp' ) ) { try { otp = ( await r.json() ).dev_code || otp; } catch ( e ) {} } } );

	await step( 'home renders', async () => {
		const r = await page.goto( BASE + '/' );
		if ( r.status() !== 200 ) { throw new Error( 'status ' + r.status() ); }
		await page.waitForSelector( '.kh-hero' );
	} );
	await step( 'smart search suggests services', async () => {
		await page.fill( '#kh-hero-search', 'عوارض' );
		await page.waitForSelector( '.kh-hero .kh-suggest li', { timeout: 5000 } );
	} );
	await step( 'citizen login via OTP', async () => {
		await page.goto( BASE + '/citizen/' );
		await page.fill( '[data-step="phone"] input[name="phone"]', phone );
		await page.click( '[data-step="phone"] button[type=submit]' );
		await page.waitForSelector( '[data-step="code"]:not([hidden])' );
		await page.screenshot( { path: OUT + '/login-code.png' } );
		await page.fill( '[data-step="code"] input[name="code"]', otp );
		await page.waitForSelector( '.bmn-dash', { timeout: 10000 } );
	} );
	await step( 'dashboard screenshot', async () => { await page.screenshot( { path: OUT + '/dashboard.png', fullPage: true } ); } );
	await step( 'save profile', async () => {
		await page.click( '.bmn-profile summary' );
		await page.fill( '#bmn-name', 'مریم احمدی' );
		await page.click( '[data-bmn-profile] button[type=submit]' );
		await page.waitForFunction( () => document.querySelector( '[data-bmn-profile] .bmn-err' ).textContent.length > 0 );
	} );
	await step( '137 report with photo + location', async () => {
		await page.goto( BASE + '/report-137/' );
		await page.check( 'input[name="category"][value="lighting"]', { force: true } );
		await page.click( '[data-bmn-locate]' );
		await page.waitForFunction( () => document.querySelector( 'input[name="lat"]' ).value !== '' );
		await page.setInputFiles( '#bmn-photo', __dirname + '/fixture.jpg' );
		await page.fill( '#bmn-desc', 'چراغ‌های کوچه از دیشب خاموش است' );
		await page.screenshot( { path: OUT + '/report-form.png', fullPage: true } );
		await page.click( '[data-bmn-request] button[type=submit]' );
		await page.waitForSelector( '.bmn-success:not([hidden])' );
		await page.screenshot( { path: OUT + '/report-done.png' } );
	} );
	await step( 'dues inquiry + sandbox payment + receipt', async () => {
		await page.goto( BASE + '/services/renovation-dues/' );
		await page.fill( '[data-bmn-dues] input[name="identifier"]', '12-04-0318-002' );
		await page.click( '[data-bmn-dues] button[type=submit]' );
		await page.waitForSelector( '.bmn-bill-card' );
		await page.screenshot( { path: OUT + '/service-bill.png', fullPage: true } );
		await page.click( '.bmn-bill-card .kh-btn' );
		await page.waitForURL( /bmn-pay\/sandbox/ );
		await page.click( 'button.ok' );
		await page.waitForURL( /bmn-pay\/receipt/ );
		const ok = await page.textContent( 'h1' );
		if ( ! /موفق/.test( ok ) ) { throw new Error( 'payment not successful' ); }
	} );
	await step( 'track by code (owner)', async () => {
		await page.goto( BASE + '/citizen/' );
		const code = ( await page.textContent( '.bmn-file .kh-help' ) ).match( /[۰-۹0-9]{10}/ )[ 0 ];
		await page.goto( BASE + '/track/' );
		await page.fill( '#bmn-tc', code );
		await page.click( '[data-bmn-track] button[type=submit]' );
		await page.waitForSelector( '.bmn-track-card' );
	} );
	await step( 'tracking shows responsible unit', async () => {
		if ( ! ( await page.textContent( '.bmn-track-card' ) ).includes( 'واحد رسیدگی‌کننده' ) ) { throw new Error( 'unit missing' ); }
	} );
	await step( 'mayor visit dossier (6 fields)', async () => {
		await page.goto( BASE + '/mayor-visits/' );
		await page.click( 'main a[href*="/mayor-visits/sample-visit"]' );
		await page.waitForSelector( '.bmn-ord' );
		const txt = await page.textContent( '.bmn-ords' );
		for ( const f of [ 'مسئلهٔ دقیق', 'دستور مشخص', 'واحد مسئول', 'مهلت اجرا', 'منبع اعتبار', 'وضعیت فعلی' ] ) {
			if ( ! txt.includes( f ) ) { throw new Error( 'missing ' + f ); }
		}
		await page.screenshot( { path: OUT + '/visit-dossier.png', fullPage: true } );
	} );
	await step( 'directives board + code lookup', async () => {
		await page.goto( BASE + '/directives/?vc=405101' );
		await page.waitForSelector( '.bmn-ord-stats' );
		if ( ( await page.$$( '.bmn-ord' ) ).length !== 1 ) { throw new Error( 'code lookup should return 1' ); }
	} );
	await step( 'minibus live map', async () => {
		await page.goto( BASE + '/minibus/' );
		await page.waitForSelector( '.bmn-fleet__route h3', { timeout: 15000 } );
		await page.waitForSelector( '.leaflet-container' );
		await page.screenshot( { path: OUT + '/minibus.png' } );
	} );
	await step( 'mobile home', async () => {
		const m = await browser.newPage( { viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 } );
		await m.goto( BASE + '/' );
		await m.screenshot( { path: OUT + '/mobile-home.png' } );
		await m.close();
	} );

	await browser.close();
	if ( errors.length ) { console.error( 'JS errors:', errors ); process.exit( 1 ); }
	console.log( 'All e2e steps passed.' );
} )().catch( ( e ) => { console.error( '\nFAILED:', e.message ); process.exit( 1 ); } );
