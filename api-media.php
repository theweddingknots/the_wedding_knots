<?php
// Start output buffering for better performance
ob_start();

// Extract and validate parameters
$gallery = $_GET['gallery'] ?? '';
$minWidth = intval($_GET['minWidth'] ?? 0);
$minHeight = intval($_GET['minHeight'] ?? 0);

// Whitelist gallery names - using constant array for better performance
const ALLOWED_GALLERIES = [
    'Gallery1' => true, 'Gallery2' => true, 'Gallery3' => true,
    'Gallery4' => true, 'Gallery5' => true, 'Gallery6' => true,
    'Gallery7' => true, 'Gallery8' => true, 'Films' => true,
    'planningtool' => true
];

// Faster validation with array key check
if (!isset(ALLOWED_GALLERIES[$gallery])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid gallery parameter']);
    exit;
}

// Define base paths
$baseDir = __DIR__ . "/$gallery";
$baseUrl = "/$gallery";

// Use array constants for file extensions
const EXT_IMAGE = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];
const EXT_VIDEO = ['mp4' => true, 'avi' => true, 'mov' => true, 'webm' => true];

// Initialize results array with capacity estimation
$media = [];

// Implement efficient caching mechanism
$cacheKey = md5($gallery . $minWidth . $minHeight);
$cacheFile = sys_get_temp_dir() . "/gallery_cache_$cacheKey.json";
$cacheLifetime = 3600; // 1 hour cache

// Check if we have a valid cache file
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    $cachedData = file_get_contents($cacheFile);
    if ($cachedData) {
        header('Content-Type: application/json');
        header('X-Cache: HIT');
        header('Cache-Control: max-age=3600, public');
        echo $cachedData;
        ob_end_flush();
        exit;
    }
}

// Scan directories more efficiently
$subDirs = glob("$baseDir/*", GLOB_ONLYDIR);

foreach ($subDirs as $folderPath) {
    $folderName = basename($folderPath);
    
    // Use RecursiveDirectoryIterator for more efficient file scanning
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        // Skip unsupported file types early
        if (!isset(EXT_IMAGE[$ext]) && !isset(EXT_VIDEO[$ext])) {
            continue;
        }
        
        $fileName = $file->getFilename();
        $url = "$baseUrl/$folderName/" . rawurlencode($fileName);
        
        if (isset(EXT_IMAGE[$ext])) {
            // Fast image dimension detection with error suppression
            $imageInfo = @getimagesize($file->getPathname());
            if (!$imageInfo) continue;
            
            [$width, $height] = $imageInfo;
            
            if ($width >= $minWidth && $height >= $minHeight) {
                $media[] = [
                    'url' => $url,
                    'type' => 'image',
                    'width' => $width,
                    'height' => $height,
                    'folder' => $folderName
                ];
            }
        } elseif (isset(EXT_VIDEO[$ext])) {
            $media[] = [
                'url' => $url,
                'type' => 'video',
                'folder' => $folderName
            ];
        }
    }
}

// Generate JSON with optimized encoding options
$jsonOutput = json_encode($media, JSON_UNESCAPED_SLASHES);

// Save to cache
file_put_contents($cacheFile, $jsonOutput);

// Set appropriate headers
header('Content-Type: application/json');
header('X-Cache: MISS');
header('Cache-Control: max-age=3600, public');
echo $jsonOutput;
ob_end_flush();