import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
        body: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Image.asset('assets/logo.png', width: 96, height: 96),
            const SizedBox(height: 16),
            const Text('ASTRO GANN',
                style: TextStyle(color: AppColors.oliveDark, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: 1)),
            const SizedBox(height: 24),
            const SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 3)),
          ]),
        ),
      );
}
