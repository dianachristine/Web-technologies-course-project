<?php
require_once 'Book.php';
require_once 'Author.php';


class Dao
{
    private PDO $connection;
    private array $books;
    private array $authors;

    public function __construct() {
        $this->connection = $this->getConnection();
        $this->authors = $this->getAuthors();
        $this->books = $this->getBooks();
    }

    private function getConnection() {

        $username = '';  // secret
        $password = '';  // secret
        $address = '';  // secret
        try {
            return new PDO($address, $username, $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            throw new RuntimeException("can't connect");
        }
    }

    function getAuthors() {
        $authors = [];

        $stmt = $this->connection ->prepare("select author.id, first_name, last_name, grade from author");
        $stmt->execute();

        foreach ($stmt as $row) {
            $author = new Author(urldecode($row['first_name']), urldecode($row['last_name']), $row['grade']);
            $author->addId($row['id']);
            $authors[] = $author;
        }

        return $authors;
    }

    function getBooks() {
        $books = [];

        $stmt = $this->connection ->prepare("select book.id, author_book.author_id, book.title, book.grade, book.is_read from book left join author_book on book.id = author_book.book_ID");
        $stmt->execute();

        foreach ($stmt as $row) {
            $inBooks = false;
            $bookObject = new Book(urldecode($row['title']), $row['grade'], $row['is_read']);
            $bookObject->addId($row['id']);

            foreach ($books as $book) {
                if (intval($book->id) === intval($row['id'])) {
                    $book->addAuthor($this->getAuthorById($row['author_id']));
                    $inBooks = true;
                }
            }

            if (!$inBooks) {
                if ($row['author_id']) {
                    $bookObject->addAuthor($this->getAuthorById($row['author_id']));
                }
                $books[] = $bookObject;
            }
        }
        return $books;
        }

    function getAuthorById($id) {

        foreach ($this->authors as $author) {
            if ($author->id === $id) {
                    return $author;
            }
        }
    }

    function addBook($book) {

        $stmt = $this->connection ->prepare("insert into book (title, grade, is_read) values (:title, :grade, :is_read)");
        $stmt->bindValue(':title', $book->title);
        $stmt->bindValue(':grade', $book->grade);
        $stmt->bindValue(':is_read', $book->isRead);
        $stmt-> execute();

        $bookId = intval($this->connection ->lastInsertId());

        $this->insertIntoAuthorBook($book->authors, $bookId);

    }

    function insertIntoAuthorBook($authors, $bookId) {

        foreach (array_reverse($authors) as $author) {
            $author_id = $author->id;
            $stmt = $this->connection ->prepare("insert into author_book (author_ID , book_ID) values (:author_ID, :book_ID)");
            $stmt->bindValue(':author_ID', $author_id);
            $stmt->bindValue(':book_ID', $bookId);
            $stmt-> execute();
        }
    }

    function getBookById($id) {

        foreach ($this->books as $book) {
            if ($book->id === $id) {
                return $book;
            }
        }
    }

    function editBook($previousAuthorIds, $book) {

        $stmt = $this->connection ->prepare("update book set title = :title, grade = :grade, is_read = :is_read where id = :id");
        $stmt->bindValue(':id', $book->id);
        $stmt->bindValue(':title', $book->title);
        $stmt->bindValue(':grade', $book->grade);
        $stmt->bindValue(':is_read', $book->isRead);
        $stmt->execute();

        for ($i = count($book->authors)-1; $i >= 0; $i--) {
            if ($previousAuthorIds[$i] && !$book->authors[$i]) { // author was deleted by user
                $stmt = $this->connection ->prepare("delete from author_book where (author_id = :author_id and book_id = :book_id) limit 1");
                $stmt->bindValue(':author_id', $previousAuthorIds[$i]);
                $stmt->bindValue(':book_id', $book->id);
                $stmt->execute();
            } else if ($this->authorChanged($previousAuthorIds[$i], $book->authors[$i])) {
                $this->updateAuthorBookTable($previousAuthorIds[$i], $book->authors[$i]->id, $book->id);
            }
        }
    }

    private function authorChanged($previousAuthorId, $newAuthor) {
        if (!$previousAuthorId && !$newAuthor or
            $newAuthor != null and $previousAuthorId === $newAuthor->id) {
            return false;
        }
        return true;
    }

    function updateAuthorBookTable($previousAuthorId, $authorId, $bookId) {

        if(!$previousAuthorId) {  // adds author
            $stmt = $this->connection ->prepare("insert into author_book (author_ID, book_ID) values (:author_id, :book_id)");
            $stmt->bindValue(':author_id', $authorId);
            $stmt->bindValue(':book_id', $bookId);
            $stmt->execute();
        } else {  // updates author
            $stmt = $this->connection ->prepare("update author_book set author_ID = :author_id where author_ID = :previous_author_id and book_ID = :book_id");
            $stmt->bindValue(':author_id', $authorId);
            $stmt->bindValue(':book_id', $bookId);
            $stmt->bindValue(':previous_author_id', $previousAuthorId);
            $stmt->execute();
        }
    }

    function deleteBook($id) {

        $stmt = $this->connection ->prepare("delete from book where id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        $stmt = $this->connection ->prepare("delete from author_book where book_id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
    }

    function addAuthor($author) {

        $stmt = $this->connection ->prepare("insert into author (first_name, last_name, grade) values (:first_name, :last_name, :grade)");
        $stmt->bindValue(':first_name', urldecode($author->firstName));
        $stmt->bindValue(':last_name', urldecode($author->lastName));
        $stmt->bindValue(':grade', $author->grade);
        $stmt->execute();
    }

    function editAuthor($author) {

        $stmt = $this->connection ->prepare("update author set first_name = :first_name, last_name = :last_name, grade = :grade where id = :id");
        $stmt->bindValue(':id', $author->id);
        $stmt->bindValue(':first_name', urldecode($author->firstName));
        $stmt->bindValue(':last_name', urldecode($author->lastName));
        $stmt->bindValue(':grade', $author->grade);
        $stmt->execute();

    }

    function deleteAuthor($id) {

        $stmt = $this->connection ->prepare("delete from author where id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();


        $stmt = $this->connection ->prepare("delete from author_book where author_id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
    }

}
