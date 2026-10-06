import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/app_theme.dart';

/// One entry: image, market date and time (spec 3.9). Tap to zoom.
class EntryCard extends StatelessWidget {
  const EntryCard({super.key, required this.entry, required this.categoryName});
  final Entry entry;
  final String categoryName;

  @override
  Widget build(BuildContext context) => Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
            fullscreenDialog: true,
            builder: (_) => ImageViewer(entry: entry, title: categoryName),
          )),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            AspectRatio(aspectRatio: 5 / 3, child: EntryImage(url: entry.thumbUrl)),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
              child: Row(children: [
                const Icon(Icons.calendar_today_rounded, size: 16, color: AppColors.olive),
                const SizedBox(width: 6),
                Text(entry.date, style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.text)),
                const Spacer(),
                const Icon(Icons.schedule_rounded, size: 16, color: AppColors.blue),
                const SizedBox(width: 6),
                Text(entry.time, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.text)),
              ]),
            ),
          ]),
        ),
      );
}

/// Network image with loading/error states. On web it uses an <img> element so images load from the API host
/// without needing CORS headers on static files.
class EntryImage extends StatelessWidget {
  const EntryImage({super.key, required this.url, this.fit = BoxFit.cover});
  final String url;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) => Image.network(
        url,
        fit: fit,
        webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
        loadingBuilder: (context, child, progress) => progress == null
            ? child
            : const ColoredBox(color: AppColors.oliveTint, child: Center(child: CircularProgressIndicator(strokeWidth: 2))),
        errorBuilder: (context, error, stack) => const ColoredBox(
          color: AppColors.oliveTint,
          child: Center(child: Icon(Icons.image_not_supported_outlined, color: AppColors.muted, size: 36)),
        ),
      );
}

class ImageViewer extends StatelessWidget {
  const ImageViewer({super.key, required this.entry, required this.title});
  final Entry entry;
  final String title;

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
          titleTextStyle: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
          title: Text('$title · ${entry.date}, ${entry.time}'),
        ),
        body: InteractiveViewer(
          maxScale: 5,
          child: Center(child: EntryImage(url: entry.imageUrl, fit: BoxFit.contain)),
        ),
      );
}
