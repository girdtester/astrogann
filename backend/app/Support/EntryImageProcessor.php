<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Daily image uploads kept in good shape (DECISIONS D-91), with PHP's GD only (available on shared hosting):
 *  - month folders: uploads/entries/2026/10/<random>.jpg (no folder with lakhs of files)
 *  - phone photos turned upright (EXIF orientation), resized to max IMAGE_MAX_PX (default 1920) and compressed
 *  - a small thumbnail (<name>_thumb.<ext>, IMAGE_THUMB_PX wide, default 640) for lists and app cards
 * If GD cannot process a file (e.g. not enough memory on the host) the original is stored unchanged, without thumb.
 */
class EntryImageProcessor
{
    /**
     * @return array{image: string, thumb: ?string} paths relative to public/ (e.g. "uploads/entries/2026/10/x.jpg")
     */
    public function store(UploadedFile $file): array
    {
        $ext = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Unsupported image type.'),
        };

        $dir = config('astrogann.entry_upload_dir').'/'.now()->format('Y/m');
        $name = Str::random(40);
        $disk = Storage::disk('uploads');
        $disk->makeDirectory($dir);
        $mainRel = "{$dir}/{$name}.{$ext}";
        $thumbRel = "{$dir}/{$name}_thumb.{$ext}";

        try {
            $img = $this->load($file->getRealPath(), $ext);
            if ($ext === 'jpg') {
                $img = $this->orient($img, $file->getRealPath());
            }
            $main = $this->fit($img, (int) config('astrogann.images.max_px', 1920));
            $this->save($main, $disk->path($mainRel), $ext);
            $thumb = $this->fit($main, (int) config('astrogann.images.thumb_px', 640), widthOnly: true);
            $this->save($thumb, $disk->path($thumbRel), $ext);

            return ['image' => 'uploads/'.$mainRel, 'thumb' => 'uploads/'.$thumbRel];
        } catch (Throwable $e) {
            report($e);
            // Fallback: keep the original bytes so the upload never fails because of image processing.
            $disk->putFileAs($dir, $file, "{$name}.{$ext}");

            return ['image' => 'uploads/'.$mainRel, 'thumb' => null];
        }
    }

    /** Create a thumbnail for an already stored image (backfill command). */
    public function thumbnailFor(string $imagePath): ?string
    {
        $rel = Str::after($imagePath, 'uploads/');
        $disk = Storage::disk('uploads');
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (! $disk->exists($rel) || ! in_array($ext, ['jpg', 'png', 'webp'], true)) {
            return null;
        }
        $thumbRel = preg_replace('/\.[a-z]+$/i', "_thumb.{$ext}", $rel);
        $img = $this->load($disk->path($rel), $ext);
        $this->save($this->fit($img, (int) config('astrogann.images.thumb_px', 640), widthOnly: true), $disk->path($thumbRel), $ext);

        return 'uploads/'.$thumbRel;
    }

    private function load(string $path, string $ext): GdImage
    {
        $img = match ($ext) {
            'jpg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
        };
        if (! $img instanceof GdImage) {
            throw new \RuntimeException('Could not read the image.');
        }

        return $img;
    }

    /** Turn phone photos upright according to their EXIF orientation. */
    private function orient(GdImage $img, string $path): GdImage
    {
        $o = function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        return match ((int) $o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    /** Shrink so the longest side (or only the width) is at most $max px; never enlarges. */
    private function fit(GdImage $img, int $max, bool $widthOnly = false): GdImage
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        $scale = $widthOnly ? $max / $w : $max / max($w, $h);
        if ($scale >= 1) {
            return $img;
        }
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    private function save(GdImage $img, string $absolute, string $ext): void
    {
        $quality = (int) config('astrogann.images.quality', 85);
        $ok = match ($ext) {
            'jpg' => (function () use ($img, $absolute, $quality) {
                imageinterlace($img, true); // progressive JPEG: shows quickly on slow mobile data

                return imagejpeg($img, $absolute, $quality);
            })(),
            'png' => imagepng($img, $absolute, 6),
            'webp' => imagewebp($img, $absolute, $quality),
        };
        if (! $ok) {
            throw new \RuntimeException('Could not save the image.');
        }
    }
}
