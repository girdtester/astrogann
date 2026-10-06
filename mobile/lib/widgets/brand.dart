import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Logo + app name (name comes from GET /api/settings).
class BrandTitle extends StatelessWidget {
  const BrandTitle({super.key, required this.appName, this.size = 32});
  final String appName;
  final double size;

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        Image.asset('assets/logo.png', width: size, height: size),
        const SizedBox(width: 10),
        Flexible(
          child: Text(appName, overflow: TextOverflow.ellipsis,
              style: TextStyle(color: AppColors.oliveDark, fontWeight: FontWeight.w800, fontSize: size * 0.56, letterSpacing: 0.6)),
        ),
      ]);
}
