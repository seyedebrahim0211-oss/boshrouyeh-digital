import 'package:boshrouyeh_man/theme/brand.dart';
import 'package:boshrouyeh_man/widgets/brand_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Brand widgets render in RTL light & dark', (tester) async {
    for (final b in [Brightness.light, Brightness.dark]) {
      await tester.pumpWidget(MaterialApp(
        theme: Brand.theme(b),
        home: const Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            body: Column(children: [
              Taqche(icon: 'payment'),
              StatusChip('انجام شد', kind: 'done'),
              StepProgress(3),
              SectionTitle('تازه‌ترین اخبار'),
              StateView.empty('خالی'),
            ]),
          ),
        ),
      ));
      expect(find.text('انجام شد'), findsOneWidget);
      expect(find.text('تازه‌ترین اخبار'), findsOneWidget);
    }
  });
}
