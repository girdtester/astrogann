import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/app_theme.dart';

/// Entries as a table: Sr. No | Date | Time | Image (D-101). Small eye icon on the preview; tap for the full HD picture.
class EntryTable extends StatelessWidget {
  const EntryTable({super.key, required this.entries, required this.categoryName});
  final List<Entry> entries;
  final String categoryName;

  static const _head = TextStyle(fontWeight: FontWeight.w800, color: AppColors.oliveDark, fontSize: 13);

  void _open(BuildContext context, Entry e) => Navigator.of(context).push(MaterialPageRoute<void>(
        fullscreenDialog: true,
        builder: (_) => ImageViewer(entry: e, title: categoryName),
      ));

  @override
  Widget build(BuildContext context) => Card(
        clipBehavior: Clip.antiAlias,
        child: Table(
          key: const Key('entry-table'),
          columnWidths: const {
            0: FixedColumnWidth(52),
            1: FlexColumnWidth(1.25),
            2: FlexColumnWidth(1),
            3: FixedColumnWidth(118),
          },
          defaultVerticalAlignment: TableCellVerticalAlignment.middle,
          border: const TableBorder(horizontalInside: BorderSide(color: Color(0xFFE5E7EB))),
          children: [
            const TableRow(
              decoration: BoxDecoration(color: AppColors.oliveTint),
              children: [
                Padding(padding: EdgeInsets.fromLTRB(12, 10, 4, 10), child: Text('Sr.', style: _head)),
                Padding(padding: EdgeInsets.symmetric(horizontal: 6, vertical: 10), child: Text('Date', style: _head)),
                Padding(padding: EdgeInsets.symmetric(horizontal: 6, vertical: 10), child: Text('Time', style: _head)),
                Padding(padding: EdgeInsets.fromLTRB(6, 10, 12, 10), child: Text('Image', style: _head)),
              ],
            ),
            for (var i = 0; i < entries.length; i++)
              TableRow(children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 8, 4, 8),
                  child: Text('${i + 1}', style: const TextStyle(color: AppColors.muted, fontWeight: FontWeight.w600)),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
                  child: Text(entries[i].date, style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.text)),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
                  child: Text(entries[i].time, style: const TextStyle(color: AppColors.text)),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(6, 8, 12, 8),
                  child: InkWell(
                    key: Key('entry-image-${entries[i].id}'),
                    borderRadius: BorderRadius.circular(8),
                    onTap: () => _open(context, entries[i]),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Stack(children: [
                        SizedBox(width: 100, height: 62, child: EntryImage(url: entries[i].thumbUrl)),
                        Positioned(
                          right: 4,
                          bottom: 4,
                          child: Container(
                            padding: const EdgeInsets.all(3),
                            decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(4)),
                            child: const Icon(Icons.visibility_outlined, size: 14, color: Colors.white),
                          ),
                        ),
                      ]),
                    ),
                  ),
                ),
              ]),
          ],
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
        // Full HD image (never the thumbnail); pinch / double-tap area to zoom up to 8×.
        body: InteractiveViewer(
          minScale: 1,
          maxScale: 8,
          child: Center(child: EntryImage(url: entry.imageUrl, fit: BoxFit.contain)),
        ),
      );
}
