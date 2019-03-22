<?php

$bot->command('img', function($message, ...$description) use ($bot) {
    if (empty($description))
        return;
    # Implode all arguments in one string.
    $description = implode(chr(32), $description);

    $images = json_decode(file_get_contents('http://ajax.googleapis.com/ajax/services/search/images?v=1.0&q=' . urlencode($description) . '&rsz=8'), true)['responseData']['results'];
    $image = fopen($images[array_rand($images)]['unescapedUrl'], 'r');

    $bot->sendPhoto($message->chat->id, $image, $description, $message->message_id);
});
