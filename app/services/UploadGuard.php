<?php

namespace App\Services;

/**
 * Safe file uploads.
 *
 * Never trust the browser: $_FILES['type'] and the original file name are
 * chosen by the uploader. We detect the real type from the file contents and
 * choose the extension ourselves, so a "photo.php" can never be saved as .php.
 */
class UploadGuard
{
    /** Detected MIME type => extension we save it as */
    const IMAGES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    const DOCUMENTS = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    /**
     * Validate an uploaded file.
     *
     * @param array $file      one entry of $_FILES
     * @param array $allowed   MIME => extension map (e.g. self::IMAGES + self::DOCUMENTS)
     * @param int   $maxBytes
     * @return array{ok:bool, ext?:string, mime?:string, message?:string}
     */
    public static function check($file, array $allowed, $maxBytes)
    {
        if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Upload failed. Please try again.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'message' => 'Invalid upload.'];
        }
        if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
            return ['ok' => false, 'message' => 'File too large (max ' . round($maxBytes / 1048576) . 'MB).'];
        }

        $mime = self::detectMime($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'message' => 'Invalid file type. Allowed: ' . strtoupper(implode(', ', array_unique(array_values($allowed)))) . '.'];
        }

        // Images must actually decode as images (blocks polyglot files with a fake header)
        if (isset(self::IMAGES[$mime]) && @getimagesize($file['tmp_name']) === false) {
            return ['ok' => false, 'message' => 'The image file is damaged or not a real image.'];
        }

        return ['ok' => true, 'ext' => $allowed[$mime], 'mime' => $mime];
    }

    public static function detectMime($path)
    {
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
        } else {
            $mime = 'application/octet-stream';
        }
        // Some servers report .docx as a generic zip
        if ($mime === 'application/zip' || $mime === 'application/octet-stream') {
            $zip = class_exists('ZipArchive') ? new \ZipArchive() : null;
            if ($zip && $zip->open($path) === true) {
                $isDocx = $zip->locateName('word/document.xml') !== false;
                $zip->close();
                if ($isDocx) {
                    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                }
            }
        }
        return $mime;
    }
}
