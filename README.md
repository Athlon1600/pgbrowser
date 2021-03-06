PGBrowser
=========

A 'pretty good' mechanize-like php library for managing cookies and submitting forms.

Read the [Documentation](https://github.com/monkeysuffrage/pgbrowser/wiki)

```php
require 'pgbrowser.php';

$b = new PGBrowser();
$page = $b->get('http://www.google.com/');
$form = $page->form();
$form->set('q', 'foo');
$page = $form->submit();
echo $page->title;
```

Now do something with $page->html or query it with $page->xpath->query()

PGBrowser will also let you query the page with phpquery, simple-html-dom, [advanced-html-dom](https://sourceforge.net/projects/advancedhtmldom/) or xpath:

```php
require 'pgbrowser.php';
require 'phpquery.php';
$browser = new PGBrowser('advanced');
$page = $browser->get('http://www.google.com/search?q=php');
foreach($page->search('li.g') as $li){
  echo $li->at('a')->text . "\n";
}
```

*New* - PGBrowser can now cache requests to disk and reuse them on subsequent requests to save network traffic. Cached responses go into a folder called 'cache'

```php
$browser->useCache = true; // turn on cacheing
$browser->useCache = false; // turn off cacheing
```

