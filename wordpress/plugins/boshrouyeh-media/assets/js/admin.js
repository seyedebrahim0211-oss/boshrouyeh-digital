/* Gallery picker for news / photo reports / forward page. */
jQuery(function ($) {
	var $f = $('#bmn_gallery'), $ul = $('#bmnm-thumbs'), frame;
	if (!$f.length) return;
	function sync() { $f.val($ul.children('li').map(function () { return $(this).data('id'); }).get().join(',')); }
	$ul.sortable({ update: sync });
	$ul.on('click', '.bmnm-x', function () { $(this).closest('li').remove(); sync(); });
	$('#bmnm-pick').on('click', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({ title: 'انتخاب تصاویر گزارش', button: { text: 'افزودن به گالری' }, library: { type: 'image' }, multiple: 'add' });
			frame.on('select', function () {
				frame.state().get('selection').each(function (a) {
					a = a.toJSON();
					if ($ul.children('[data-id="' + a.id + '"]').length) return;
					var src = (a.sizes && (a.sizes.thumbnail || a.sizes.medium || a.sizes.full)).url;
					$ul.append('<li data-id="' + a.id + '"><img src="' + src + '" alt=""><button type="button" class="bmnm-x" aria-label="حذف">×</button></li>');
				});
				sync();
			});
		}
		frame.open();
	});
});
