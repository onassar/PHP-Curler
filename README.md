PHP-Curler
===

This library was many years in the making for me. While it&#039;s usage is
limited (eg. currently only supports *get*, *post*  and *head* requests),
it&#039;s focused around the ability to quickly specific a resource to pull, and
depending on it&#039;s meta details (eg. how big the document is, what mime
types it claims itself as), proceed with the requests.

Whether or not a url is accepted can be controlled through the following methods
 - **setMime** set the acceptable mime type
 - **setMimes** set the acceptable mime types

Information about a curl request&#039;s error can be accessed through the
**getErrors** method. Additionally, raw information about the request can be
accessed through the **getInfo** method.

### Example Output of a URL

``` php
<?php

    // booting
    require_once APP . '/vendors/PHP-Curler/Curler.class.php';
    
    // grab google.com contents and display
    $curler = new Curler();
    echo $curler->get('http://www.google.com/');
    exit(0);

```

The above example, quite simply, downloads the google.com source, and displays
it. By default, every new **Curler** instance is configured to accept documents
whose mime type falls into the category of &#039;webpage&#039; (which includes
the mime types application/xhtml+xml and text/html).
