import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../core/khesht_icons_data.dart';

/// Brand line icon from the shared Khesht icon set.
class KIcon extends StatelessWidget {
  const KIcon(this.name, {super.key, this.size = 24, this.color});

  final String name;
  final double size;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final path = kheshtIconPaths[name] ?? kheshtIconPaths['grid']!;
    final c = color ?? IconTheme.of(context).color ?? Theme.of(context).colorScheme.onSurface;
    return SvgPicture.string(
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">$path</svg>',
      width: size,
      height: size,
      colorFilter: ColorFilter.mode(c, BlendMode.srcIn),
      excludeFromSemantics: true,
    );
  }
}
