<?php

namespace App\Console\Commands;

use App\Models\Entry;
use App\Support\EntryImageProcessor;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-off backfill (D-91): create thumbnails for entries uploaded before thumbnails existed. Safe to run again.
 */
class MakeThumbnails extends Command
{
    protected $signature = 'astrogann:make-thumbnails {--limit=0 : Stop after this many (0 = all)}';

    protected $description = 'Create small preview images for entries that do not have one yet';

    public function handle(EntryImageProcessor $images): int
    {
        $done = $failed = 0;
        $limit = (int) $this->option('limit');

        Entry::query()->whereNull('thumb_path')->select(['id', 'image_path'])->chunkById(200, function ($entries) use ($images, &$done, &$failed, $limit) {
            foreach ($entries as $entry) {
                try {
                    if ($thumb = $images->thumbnailFor($entry->image_path)) {
                        Entry::whereKey($entry->id)->update(['thumb_path' => $thumb]);
                        $done++;
                    }
                } catch (Throwable) {
                    $failed++;
                }
                if ($limit && $done >= $limit) {
                    return false;
                }
            }
        });

        $this->info("Created {$done} thumbnail(s)".($failed ? ", {$failed} could not be read" : '').'.');

        return self::SUCCESS;
    }
}
