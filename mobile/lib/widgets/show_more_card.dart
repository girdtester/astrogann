import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/models.dart';
import '../theme/app_theme.dart';

/// "Show More" — contact the admin for full access (spec 3.4). Text and numbers come from the server.
class ShowMoreCard extends StatefulWidget {
  const ShowMoreCard({super.key, required this.showMore, this.initiallyOpen = false});
  final ShowMore showMore;
  final bool initiallyOpen;

  @override
  State<ShowMoreCard> createState() => _ShowMoreCardState();
}

class _ShowMoreCardState extends State<ShowMoreCard> {
  late bool _open = widget.initiallyOpen;

  Future<void> _launch(Uri uri) async {
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Could not open ${uri.toString()}')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.showMore;
    return Container(
      decoration: BoxDecoration(
        color: AppColors.yellowLight,
        border: Border.all(color: const Color(0xFFFDD847)),
        borderRadius: BorderRadius.circular(14),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          const Icon(Icons.lock_open_rounded, color: AppColors.yellowDark),
          const SizedBox(width: 10),
          Expanded(child: Text(s.text, style: const TextStyle(color: AppColors.yellowDark, fontWeight: FontWeight.w500))),
        ]),
        const SizedBox(height: 12),
        FilledButton.icon(
          key: const Key('show-more-button'),
          style: FilledButton.styleFrom(backgroundColor: AppColors.yellow, foregroundColor: AppColors.text),
          onPressed: () => setState(() => _open = !_open),
          icon: Icon(_open ? Icons.expand_less : Icons.expand_more),
          label: const Text('Show More'),
        ),
        AnimatedCrossFade(
          duration: const Duration(milliseconds: 200),
          crossFadeState: _open ? CrossFadeState.showSecond : CrossFadeState.showFirst,
          firstChild: const SizedBox(width: double.infinity),
          secondChild: Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Wrap(spacing: 8, runSpacing: 8, children: [
              for (final p in s.phones)
                _ContactChip(icon: Icons.call_rounded, label: 'Call ${p.label}', color: AppColors.blue,
                    onTap: () => _launch(Uri(scheme: 'tel', path: p.target))),
              for (final w in s.whatsapp)
                _ContactChip(icon: Icons.chat_rounded, label: 'WhatsApp ${w.label}', color: AppColors.olive,
                    onTap: () => _launch(Uri.parse(w.target))),
              if (s.email != null)
                _ContactChip(icon: Icons.mail_rounded, label: s.email!, color: AppColors.blue,
                    onTap: () => _launch(Uri(scheme: 'mailto', path: s.email))),
            ]),
          ),
        ),
      ]),
    );
  }
}

class _ContactChip extends StatelessWidget {
  const _ContactChip({required this.icon, required this.label, required this.color, required this.onTap});
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10), side: const BorderSide(color: AppColors.border)),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(icon, size: 18, color: color),
              const SizedBox(width: 8),
              Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w600)),
            ]),
          ),
        ),
      );
}
