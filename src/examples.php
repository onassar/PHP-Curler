<?php

    // Passes
    $curler = new Curler();
    $curler->get('http://www.google.com/');


    // Passes
    $curler = new Curler(array(
        'validMimeTypes' => 'webpages'
    ));
    $curler->get('http://www.google.com/');


    // Passes
    $curler = new Curler(array(
        'validMimeTypes' => 'text/html'
    ));
    $curler->get('http://www.google.com/');


    // Fails (mime type)
    $curler = new Curler();
    $curler->get('http://www.google.ca/intl/en/images/about_logo.gif');
    print_r($curler->getError());


    // Passes
    $curler = new Curler(array(
        'validMimeTypes' => 'images'
    ));
    $curler->get('http://www.google.ca/intl/en/images/about_logo.gif');


    // Passes
    $curler = new Curler(array(
        'validMimeTypes' => 'gif'
    ));
    $curler->get('http://www.google.ca/intl/en/images/about_logo.gif');


    // Passes
    $curler = new Curler(array(
        'validMimeTypes' => 'image/gif'
    ));
    $curler->get('http://www.google.ca/intl/en/images/about_logo.gif');

    // $curler = new Curler();
    // $curler->setMime('image/jpeg');
    // $curler->get('http://www.google.ca/intl/en/images/about_logo.gif'); // fails

    // $curler = new Curler();
    // $curler->get('https://graph.facebook.com/oliver.nassar/picture'); // fails, since response is image/javascript

    // $curler = new Curler();
    // $curler->setMime('image');
    // $curler->get('https://graph.facebook.com/oliver.nassar/picture'); // fails if javascript, passes otherwise

    // $curler = new Curler();
    // $curler->setMime('image/jpeg');
    // $curler->get('https://graph.facebook.com/oliver.nassar/picture'); // fails if javascript, passes otherwise

    // $curler = new Curler();
    // $curler->setMime('javascript');
    // $curler->get('https://graph.facebook.com/oliver.nassar/picture'); // fails if image, passes otherwise

    // $curler = new Curler();
    // $curler->setMime('text/javascript');
    // $curler->get('https://graph.facebook.com/oliver.nassar/picture'); // fails if image, passes otherwise

    // $curler = new Curler();
    // $curler->setMimes('image', 'javascript');
    // $curler->get('https://graph.facebook.com/oliver.nassar/picture'); // passes

    // $curler = new Curler();
    // $curler->setMime('all');
    // $curler->get('https://graph.facebook.com/oliver.nassar/picture'); // passes

    // // <POST> requests
    // $curler = new Curler();
    // $curler->head('http://www.google.com/'); // passes (theoretically; head call will go through)

    // $curler = new Curler();
    // $curler->setMimes('image', 'javascript');
    // $curler->head('http://www.google.com/'); // fails (theoretically; head call will go through)

    // $curler = new Curler();
    // $curler->head('http://www.google.ca/intl/en/images/about_logo.gif'); // passes (theoretically; head call will go through)

    // $curler = new Curler();
    // $curler->head('graph.facebook.com/oliver.nassar/picture'); // passes (theoretically; head call will go through)
    //      * </code>
