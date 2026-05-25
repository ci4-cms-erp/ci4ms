<?php

return [
    'backendThemes' => 'Themes',
    'backendTheme'=>'Theme',
    'zipOpenFailed' => 'ZIP file could not be opened',
    'foldersWithSameNameHeader' => '<h3>Folders with the same name:</h3>',
    'foldersWithSameNameMessage' => '<strong>{0}</strong> exists in the following folders:<ul>',
    'foldersWithSameNameListItem' => '<li>{0}</li>',
    'forbiddenFileInZip'          => 'Forbidden file type detected in the ZIP archive: {0}. Only static asset files (css, js, images, fonts, xml, json) are allowed under the public/ directory. PHP files are not permitted.',
    'pathTraversalDetected'       => 'Potential path traversal attack detected in the ZIP archive entry: {0}',
    'zipBombDetected'             => 'The uploaded archive is too large or contains an oversized entry ({0}). Refusing to extract.',
    'symlinkRejected'             => 'The uploaded archive contains a symbolic link ({0}). Symlinks are not allowed in theme packages.',
    'metadataMissing'             => 'The uploaded archive does not contain a valid info.xml or screenshot.png at the expected location.',
    'invalidSlug'                 => 'The info.xml file does not declare a valid <slug>. Slug must match [a-z0-9_-]+ and be at most 64 characters.',
    'tempDirExists'               => 'A previous install attempt for theme "{0}" left a temporary directory behind. Remove it before retrying.',
    'screenshotInvalid'           => 'The screenshot.png inside the uploaded archive is not a valid PNG image.',
];
