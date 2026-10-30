<?php
const SECURITY_IMAGE_MAX_BYTES = 5242880;

function security_png_container($content) {
    if (substr($content, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;
    $offset = 8;
    $sawHeader = false;
    $compressed = '';
    while ($offset + 12 <= strlen($content)) {
        $length = unpack('Nlength', substr($content, $offset, 4))['length'];
        if ($length > strlen($content) - $offset - 12) return false;
        $type = substr($content, $offset + 4, 4);
        $data = substr($content, $offset + 8, $length);
        $crc = substr($content, $offset + 8 + $length, 4);
        if (!preg_match('/^[A-Za-z]{4}$/D', $type) || pack('N', crc32($type . $data)) !== $crc) return false;
        if (!$sawHeader && ($type !== 'IHDR' || $length !== 13)) return false;
        if ($type === 'IHDR') {
            if ($sawHeader) return false;
            $sawHeader = true;
        }
        if ($type === 'IDAT') $compressed .= $data;
        $offset += $length + 12;
        if ($type === 'IEND') {
            return $sawHeader && $length === 0 && $offset === strlen($content) && $compressed !== '' && @gzuncompress($compressed, 67108864) !== false;
        }
    }
    return false;
}

function security_validate_image(array $file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Choose a valid image upload (maximum 5 MB).');
    }
    $bytes = filesize($file['tmp_name']);
    if ($bytes === false || $bytes < 12 || $bytes > SECURITY_IMAGE_MAX_BYTES) throw new InvalidArgumentException('Image must be no larger than 5 MB.');
    $name = $file['name'] ?? null;
    if (!is_string($name) || preg_match('/\.(?:php[0-9]*|phtml|phar|cgi|pl|py|asp|aspx|shtml|htaccess)(?:\.|$)/i', $name)) throw new InvalidArgumentException('Executable filenames are not allowed.');
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $formats = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    if (!isset($formats[$extension])) throw new InvalidArgumentException('Choose a JPEG, PNG, GIF or WebP image.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $mime !== $formats[$extension] || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1 || $info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 16000000) {
        throw new InvalidArgumentException('Image content must match its extension and be at most 8000 pixels per side and 16 megapixels.');
    }
    $content = file_get_contents($file['tmp_name']);
    // Reject embedded PHP and incomplete file containers; never use client MIME.
    if (strpos($content, '<?') !== false) throw new InvalidArgumentException('Embedded executable content is not allowed.');
    $complete = false;
    if ($mime === 'image/png') {
        $complete = security_png_container($content);
    } elseif ($mime === 'image/jpeg') {
        $complete = substr($content, 0, 2) === "\xff\xd8" && substr($content, -2) === "\xff\xd9";
    } elseif ($mime === 'image/gif') {
        $complete = in_array(substr($content, 0, 6), ['GIF87a', 'GIF89a'], true) && substr($content, -1) === "\x3b";
    } elseif ($mime === 'image/webp') {
        $complete = substr($content, 0, 4) === 'RIFF' && substr($content, 8, 4) === 'WEBP' && unpack('Vsize', substr($content, 4, 4))['size'] + 8 === $bytes;
    }
    if (!$complete) throw new InvalidArgumentException('Image file is incomplete or invalid.');
    return ['mime' => $mime, 'extension' => $mime === 'image/jpeg' ? 'jpg' : $extension, 'bytes' => $bytes];
}

function security_store_image(array $file, $relativeDirectory) {
    $validated = security_validate_image($file);
    if (!is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Invalid upload source.');
    if (!in_array($relativeDirectory, ['uploads', 'assets/uploads'], true)) throw new RuntimeException('Invalid upload destination.');
    $folder = __DIR__ . '/../' . $relativeDirectory;
    if (!is_dir($folder) || !is_file($folder . '/.htaccess')) throw new RuntimeException('Protected upload directory is unavailable.');
    $filename = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) throw new RuntimeException('Unable to store image.');
    return $relativeDirectory . '/' . $filename;
}
