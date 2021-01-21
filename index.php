<?php
require_once 'vendor/tpl.php';
require_once 'Request.php';
require_once 'Dao.php';

$request = new Request($_REQUEST);

$cmd = $request->param('cmd')
    ? $request->param('cmd')
    : 'show_book_list';

if ($cmd === 'show_book_form') {
    $dao = new Dao();
    $authors = $dao->getAuthors();

    $errors = [];
    $book = new book("", 0, 0);

    if (isset($_POST["submitButton"])) {
        $book = new Book(
            isset($_POST["title"]) ? $_POST["title"] : "",
            isset($_POST["grade"]) ? $_POST["grade"] : 0,
            isset($_POST["isRead"]) ? $_POST["isRead"] : 0);

        $author_id1 = $_POST["author1"];
        $author_id2 = $_POST["author2"];

        if ($_POST["author1"]) {
            $book->addAuthor($dao->getAuthorById($_POST["author1"]));
        }
        if($_POST["author2"]) {
            $book->addAuthor($dao->getAuthorById($_POST["author2"]));
        }
        if (strlen($book->title) < 3 || strlen($book->title) > 23) {
            $errors[] = "Pealkirja pikkus peab olema 3 kuni 23 tähemärki";
        }
    }

    if (isset($_POST["submitButton"]) and empty($errors)) {
        $dao->addBook($book);
        header("Location: ?cmd=show_book_list&message=Lisatud!");

    } else {
        $data = [
            'book' => $book,
            'authors' => $authors,
            'errors' => $errors,
            'contentPath' => 'book-add.html',
            'cmd' => $cmd
        ];

        print renderTemplate('tpl/main.html', $data);
    }

} else if ($cmd === 'show_author_form') {
    $dao = new Dao();

    $errors = [];
    $author = new Author("", "", 0);

    if (isset($_POST["submitButton"])) {
        $author = new Author(
            isset($_POST["firstName"]) ? $_POST["firstName"] : "",
            isset($_POST["lastName"]) ? $_POST["lastName"] : "",
            isset($_POST["grade"]) ? $_POST["grade"] : 0
        );

        if (strlen($author->firstName) < 1 || strlen($author->firstName) > 21) {
            $errors[] = "Eesnime pikkus peab olema 1 kuni 21 tähemärki";
        }
        if (strlen($author->lastName) < 2 || strlen($author->lastName) > 22) {
            $errors[] = "Perekonnanime pikkus peab olema 2 kuni 22 tähemärki";
        }
    }

    if (isset($_POST["submitButton"]) and empty($errors)) {
        $dao->addAuthor($author);
        header("Location: ?cmd=show_author_list&message=Lisatud!");

    } else {
        $data = [
            'author' => $author,
            'errors' => $errors,
            'contentPath' => 'author-add.html',
            'cmd' => $cmd
        ];

        print renderTemplate('tpl/main.html', $data);
    }

} else if ($cmd === 'edit_book') {
    $dao = new Dao();
    $authors = $dao->getAuthors();
    $errors = [];
    $book = [];
    $previousAuthorIds = [];

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $book = new Book($_POST["title"],
            isset($_POST["grade"]) ? $_POST["grade"] : 0,
            isset($_POST["isRead"]) ? $_POST["isRead"] : '0');
        $book->addId($_POST["id"]);

        $book->addAuthor(isset($_POST["author1"]) ? $dao->getAuthorById($_POST["author1"]) : null);
        $book->addAuthor(isset($_POST["author2"]) ? $dao->getAuthorById($_POST["author2"]) : null);

        $previousAuthorIds[] = $_POST["previous-author1"] ? intval($_POST["previous-author1"]) : '';
        $previousAuthorIds[] = $_POST["previous-author2"] ? intval($_POST["previous-author2"]) : '';

        if (strlen($book->title) < 3 || strlen($book->title) > 23) {
            $errors[] = "Pealkirja pikkus peab olema 3 kuni 23 tähemärki";
            $book->title = $_POST["originalTitle"];
        }

    } else if ($_SERVER["REQUEST_METHOD"] === "GET"){
        if (isset($_GET["id"])) {
            $book = $dao->getBookById($_GET["id"]);
        }
    }

    if (isset($_POST["submitButton"]) and empty($errors)) {
        $dao->editBook($previousAuthorIds, $book);
        header("Location: ?cmd=show_book_list&message=Muudetud!");
    } else {
        $data = [
            'book' => $book,
            'authors' => $authors,
            'errors' => $errors,
            'contentPath' => 'edit-book.html',
            'cmd' => $cmd
        ];

        print renderTemplate('tpl/main.html', $data);
    }

} else if ($cmd === 'edit_author') {
    $dao = new Dao();
    $author = new Author("", "", 0);
    $errors = [];

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $author = new Author($_POST["firstName"], $_POST["lastName"], $_POST["grade"]);
        $author->addId($_POST["id"]);
        $originalFirstName = $_POST["originalFirstName"];

        if (strlen($author->firstName) < 1 || strlen($author->firstName) > 21) {
            $errors[] = "Eesnime pikkus peab olema 1 kuni 21 tähemärki";
            $author->firstName = $originalFirstName;
        }
        if (strlen($author->lastName) < 2 || strlen($author->lastName) > 22) {
            $errors[] = "Perekonnanime pikkus peab olema 2 kuni 22 tähemärki";
        }

    } else if ($_SERVER["REQUEST_METHOD"] === "GET"){
        if (isset($_GET["id"])) {
            $author = $dao->getAuthorById($_GET["id"]);
        }
    }

    if (isset($_POST["submitButton"]) and empty($errors)) {
        $dao->editAuthor($author);
        header("Location: ?cmd=show_author_list&message=Muudetud!");

    } else {
        $data = [
            'author' => $author,
            'errors' => $errors,
            'contentPath' => 'edit-author.html',
            'cmd' => $cmd
        ];

        print renderTemplate('tpl/main.html', $data);
    }

} else if ($cmd === 'delete_book') {
    $dao = new Dao();

    if (isset($_POST["deleteButton"]) && isset($_POST["book-to-delete"])) {
        $bookToDelete = $_POST["book-to-delete"];

        $dao->deleteBook($bookToDelete);
    }
    header("Location: ?cmd=show_book_list&message=Kustutatud!");

} else if ($cmd === 'delete_author') {
    $dao = new Dao();

    if (isset($_POST["deleteButton"]) && isset($_POST["author-to-delete"])) {
        $authorToDelete = $_POST["author-to-delete"];

        $dao->deleteAuthor($authorToDelete);
    }
    header("Location: ?cmd=show_author_list&message=Kustutatud!");
}

if ($cmd === 'show_book_list') {
    $dao = new Dao();
    $books = $dao->getBooks();
    $messages = [];

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        if (isset($_GET['message'])) {
            $messages[] = $_GET['message'];
        }
    }

    $data = [
        'books' => $books,
        'messages' => $messages,
        'contentPath' => 'book-list.html'
    ];

    print renderTemplate('tpl/main.html', $data);

} else if ($cmd === 'show_author_list') {
    $dao = new Dao();
    $authors = $dao->getAuthors();
    $messages = [];

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        if (isset($_GET['message'])) {
            $messages[] = $_GET['message'];
        }
    }

    $data = [
        'authors' => $authors,
        'messages' => $messages,
        'contentPath' => 'author-list.html'
    ];

    print renderTemplate('tpl/main.html', $data);
}
