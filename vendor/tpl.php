<?php

// from: https://github.com/mkalmo/php-ng-template

namespace tplLib {


class Token {

    public $type;
    public $text;

    public function __construct($type, $text) {
        $this->type = $type;
        $this->text = $text;
    }
}

}
namespace tplLib {


class ParseException extends \RuntimeException {

    public $message;
    public $pos;

    public function __construct($message, $pos) {
        parent::__construct($message);

        $this->pos = $pos;
    }
}
}
namespace tplLib {



class HtmlLexer {

    private $p;
    private $c;
    private $input;
    private $tokens = [];

    const EOF_CHAR = '<EOF>';
    const EOF_TYPE = 'EOF_TYPE';

    const TAG_OPEN = 'TAG_OPEN';
    const TAG_CLOSE = 'TAG_CLOSE';
    const TAG_SLASH_CLOSE = 'TAG_SLASH_CLOSE';
    const TAG_NAME = 'TAG_NAME';
    const TAG_SLASH = 'TAG_SLASH';
    const TAG_EQUALS = 'TAG_EQUALS';

    const SEA_WS = 'SEA_WS';
    const TAG_WS = 'TAG_WS';
    const HTML_TEXT = 'HTML_TEXT';
    const HTML_COMMENT = 'HTML_COMMENT';
    const SCRIPT = 'SCRIPT';
    const DTD = 'DTD';
    const XML_DECLARATION = 'XML_DECLARATION';

    const DOUBLE_QUOTE_STRING = 'DOUBLE_QUOTE_STRING';
    const SINGLE_QUOTE_STRING = 'SINGLE_QUOTE_STRING';
    const UNQUOTED_STRING = 'UNQUOTED_STRING';

    public function __construct($input) {
        $this->input = $input;
        $this->p = -1;
        $this->consume();
    }

    public function tokenize() {

        while ($this->c !== self::EOF_CHAR) {
            if ($this->isMatch('<!--')) {
                $this->HTML_COMMENT();
            } else if ($this->isMatch('<!')) {
                $this->DTD();
            } else if ($this->isMatch('<?xml')) {
                $this->XML_DECLARATION();
            } else if ($this->isMatch('<script')) {
                $this->SCRIPT();
            } else if ($this->c === '<') {
                $this->TAG();
            }  else if ($this->isWS()) {
                $this->WS(self::SEA_WS);
            } else {
                $this->HTML_TEXT();
            }
        }

        return $this->tokens;
    }

    private function isWS() {
        return $this->c === " "
            || $this->c === "\t"
            || $this->c === "\r"
            || $this->c === "\n";
    }

    private function WS($wsType) {
        $contents = '';
        while ($this->isWS()) {
            $contents .= $this->c;
            $this->consume();
        }

        $this->tokens[] = new Token($wsType, $contents);
    }

    private function HTML_TEXT() {
        $contents = '';
        while ($this->c !== '<' && $this->c !== self::EOF_CHAR) {
            $contents .= $this->c;
            $this->consume();
        }

        $this->tokens[] = new Token(self::HTML_TEXT, $contents);
    }

    private function DTD() {
        $contents = $this->matchBetweenStrings('<!', '>');
        $this->tokens[] = new Token(self::DTD, $contents);
    }

    private function XML_DECLARATION() {
        $contents = $this->matchBetweenStrings('<?xml', '>');
        $this->tokens[] = new Token(self::XML_DECLARATION, $contents);
    }

    private function HTML_COMMENT() {
        $contents = $this->matchBetweenStrings('<!--', '-->');
        $this->tokens[] = new Token(self::HTML_COMMENT, $contents);
    }

    private function SCRIPT() {
        $contents = $this->matchBetweenStrings('<script', '</script>');
        $this->tokens[] = new Token(self::SCRIPT, $contents);
    }

    private function TAG() {
        $this->match('<');
        $this->tokens[] = new Token(self::TAG_OPEN, '<');

        while ($this->c !== '>') {

            if ($this->c === self::EOF_CHAR) {
                $this->throwException("tag started but not closed");
            }

            if ($this->isLETTER()) {
                $this->TAG_NAME();
            } else if ($this->c === '=') {
                $this->ATTVALUE();
            } else if ($this->isMatch('/>')) {
                $this->match('/>');
                $this->tokens[] = new Token(self::TAG_SLASH_CLOSE, '/>');
                return;
            } else if ($this->c === '/') {
                $this->consume();
                $this->tokens[] = new Token(self::TAG_SLASH, '/');
            } else if ($this->isWS()) {
                $this->WS(self::TAG_WS);
            } else {
                $this->throwException(sprintf('invalid character: %s', $this->c));
            }
        }

        $this->match('>');
        $this->tokens[] = new Token(self::TAG_CLOSE, '>');
    }

    private function ATTVALUE() {
        $this->match('=');
        $this->tokens[] = new Token(self::TAG_EQUALS, '=');

        if ($this->isWS()) {
            $this->WS(self::TAG_WS);
        }

        if ($this->c === "'") {
            $this->SINGLE_QUOTE_STRING();
        } else if ($this->c === '"') {
            $this->DOUBLE_QUOTE_STRING();
        } else {
            $this->UNQUOTED_STRING();
        }
    }

    private function TAG_NAME() {
        $name = '';

        do {
            $name .= $this->c;
            $this->consume();
        } while ($this->isTAG_NAME_CHAR());

        $this->tokens[] = new Token(self::TAG_NAME, $name);
    }

    private function SINGLE_QUOTE_STRING() {
        $contents = $this->matchBetweenStrings("'", "'");
        $this->tokens[] = new Token(self::SINGLE_QUOTE_STRING, $contents);
    }

    private function DOUBLE_QUOTE_STRING() {
        $contents = $this->matchBetweenStrings('"', '"');
        $this->tokens[] = new Token(self::DOUBLE_QUOTE_STRING, $contents);
    }

    private function UNQUOTED_STRING() {
        $contents = '';
        while (!$this->isWS()
            && $this->c !== '>'
            && $this->c !== self::EOF_CHAR) {

            $contents .= $this->c;
            $this->consume();
        }

        $this->tokens[] = new Token(self::UNQUOTED_STRING, $contents);
    }

    public function match($stringToMatch) {
        foreach (str_split($stringToMatch) as $char) {
            if ($this->c === $char) {
                $this->consume();
            } else {
                $message = sprintf(
                    'expecting: %s but found: %s', $char, $this->c);
                $this->throwException($message);
            }
        }

        return $stringToMatch;
    }

    private function throwException($message) {
        throw new ParseException(
            $message,
            $this->p);
    }

    public function consume() {
        $this->p++;
        $this->c = $this->charFromPos($this->p);
    }

    private function charFromPos($pos) {
        return $pos >= strlen($this->input)
            ? self::EOF_CHAR
            : substr($this->input, $pos, 1);

    }

    private function isMatch($stringToMatch) {
        $p = $this->p;

        foreach (str_split($stringToMatch) as $char) {

            if ($char !== $this->charFromPos($p)) {
                return false;
            }

            $p++;
        }

        return true;
    }

    public function isLETTER() {
        return ctype_alpha($this->c);
    }

    public function isTAG_NAME_CHAR() {
        return preg_match('/^[-_.:a-zA-Z0-9]$/', $this->c);
    }

    private function matchBetweenStrings($start, $end) {
        $contents = $this->match($start);

        while (!$this->isMatch($end) && $this->c !== self::EOF_CHAR) {
            $contents .= $this->c;
            $this->consume();
        }

        return $contents . $this->match($end);
    }
}


}
namespace tplLib {


class NopActions {

    public function tagStartAction($tagName, $attributes) {
    }

    public function tagEndAction($tagName) {
    }

    public function voidTagAction($tagName, $attributes, $hasSlashClose) {
    }

    public function staticElementAction($token) {
    }
}

}
namespace tplLib {



class HtmlParser {

    private $p;
    private $input;
    private $actions;
    private $consumedPos = 0;

    public function __construct($input, $actions = null) {
        $this->input = $input;
        $this->actions = $actions !== null ? $actions : new NopActions();
        $this->p = 0;
    }

    public function parse() {
        $this->htmlDocument();
    }

    public function parseFragment() {
        $this->htmlContent();
    }

    private function htmlDocument() {
        // htmlDocument
        //    : SEA_WS? xml? SEA_WS? dtd? SEA_WS? htmlElements*

        $this->optionalElement(HtmlLexer::SEA_WS);
        $this->optionalElement(HtmlLexer::DTD);
        $this->optionalElement(HtmlLexer::XML_DECLARATION);
        $this->optionalElement(HtmlLexer::SEA_WS);
        $this->optionalElement(HtmlLexer::HTML_TEXT);

        while ($this->isHtmlElements()) {
            $this->htmlElements();
        }

        $this->optionalElement(HtmlLexer::HTML_TEXT);
    }

    private function htmlElements() {
        // htmlElements : htmlMisc* htmlElement htmlMisc*

        while ($this->isHtmlMisc()) {
            $this->htmlMisc();
        }

        $this->htmlElement();

        while ($this->isHtmlMisc()) {
            $this->htmlMisc();
        }
    }

    private function htmlElement() {
        // htmlElement : TAG_OPEN .. | script | style;

        if ($this->ltt() === HtmlLexer::SCRIPT) {
            $this->actions->staticElementAction($this->lt());
            $this->consume();
            return;
        }

        $this->match(HtmlLexer::TAG_OPEN);
        $tagName = $this->lt()->text;
        $this->match(HtmlLexer::TAG_NAME);

        $this->optionalTagSpace();

        $attributes = [];
        while ($this->ltt() === HtmlLexer::TAG_NAME) {
            list ($key, $value) = $this->htmlAttribute();
            $attributes[$key] = $value;
        }

        if ($this->isVoidTag($tagName)) {
            $hasSlashClose = false;
            if ($this->ltt() === HtmlLexer::TAG_SLASH_CLOSE) {
                $hasSlashClose = true;
                $this->consume();
            } else {
                $this->match(HtmlLexer::TAG_CLOSE);
            }

            $this->actions->voidTagAction($tagName, $attributes, $hasSlashClose);
            return;
        }

        $this->match(HtmlLexer::TAG_CLOSE);

        $this->actions->tagStartAction($tagName, $attributes);

        $this->htmlContent();

        $this->matchCloseOf($tagName);

        $this->actions->tagEndAction($tagName);
    }

    private function htmlContent() {
        // htmlContent : htmlChardata? ((htmlElement | xhtmlCDATA | htmlComment) htmlChardata?)*

        if ($this->isHtmlChardata()) {
            $this->htmlChardata();
        }

        while ($this->isHtmlElement() || $this->ltt() === HtmlLexer::HTML_COMMENT) {

            if ($this->ltt() === HtmlLexer::HTML_COMMENT) {
                $this->consume();
            } else if ($this->isHtmlElement()) {
                $this->htmlElement();
            } else {
                throw new ParseException(
                    'unknown token type: ' . $this->ltt(),
                    $this->consumedPos);
            }

            if ($this->isHtmlChardata()) {
                $this->htmlChardata();
            }
        }
    }

    private function matchCloseOf($tagName) {

        $this->match(HtmlLexer::TAG_OPEN);
        $this->match(HtmlLexer::TAG_SLASH);

        $actualName = $this->ltt() === HtmlLexer::TAG_NAME
            ? $actualName = $this->lt()->text
            : null;

        $this->match(HtmlLexer::TAG_NAME);

        if ($actualName !== $tagName) {
            $message = sprintf(
                'unexpected close tag: %s (expecting: %s)', $actualName, $tagName);

            throw new ParseException($message,
                ($this->consumedPos - strlen($actualName)));
        }

        $this->match(HtmlLexer::TAG_CLOSE);
    }

    private function match($type) {
        if ($this->ltt() === $type) {
            $this->consume();
        } else {
            $message = sprintf(
                'expected: %s found: %s', $type, $this->ltt());

            throw new ParseException($message, $this->consumedPos);
        }
    }

    private function lt($lookahead = 1) {
        $p = $this->p + $lookahead - 1;

        return $p < count($this->input) ? $this->input[$p] : null;
    }

    private function ltt($lookahead = 1) {
        $token = $this->lt($lookahead);

        return $token === null ? 'EOF_TYPE' : $token->type;
    }

    private function htmlMisc() {
        // htmlMisc : htmlComment | WS;

        $this->optionalElement(HtmlLexer::HTML_COMMENT);
        $this->optionalElement(HtmlLexer::SEA_WS);
    }

    private function isHtmlMisc() {
        return $this->ltt() === HtmlLexer::HTML_COMMENT
            || $this->ltt() === HtmlLexer::SEA_WS;
    }

    private function htmlAttribute() {
        // htmlAttribute
        //    : htmlAttributeName TAG_EQUALS htmlAttributeValue
        //    | htmlAttributeName
        //    ;

        $key = $this->lt()->text;
        $value = null;

        $this->match(HtmlLexer::TAG_NAME);

        $this->optionalTagSpace();

        if ($this->ltt() === HtmlLexer::TAG_EQUALS) {
            $this->consume();

            $this->optionalTagSpace();

            if ($this->ltt() === HtmlLexer::DOUBLE_QUOTE_STRING) {
                $value = $this->lt()->text;
                $this->match(HtmlLexer::DOUBLE_QUOTE_STRING);
            } else if ($this->ltt() === HtmlLexer::SINGLE_QUOTE_STRING) {
                $value = $this->lt()->text;
                $this->match(HtmlLexer::SINGLE_QUOTE_STRING);
            } else if ($this->ltt() === HtmlLexer::UNQUOTED_STRING) {
                $value = $this->lt()->text;
                $this->match(HtmlLexer::UNQUOTED_STRING);
            } else {
                throw new ParseException(
                    'unexpected token: ' . $this->ltt(),
                    $this->consumedPos);
            }
        }

        $this->optionalTagSpace();

        return [$key, $value];
    }

    private function htmlChardata() {
        // htmlChardata : HTML_TEXT | WS;

        $this->optionalElement(HtmlLexer::SEA_WS);
        $this->optionalElement(HtmlLexer::HTML_TEXT);
    }

    private function isHtmlChardata() {
        return $this->ltt() === HtmlLexer::HTML_TEXT
            || $this->ltt() === HtmlLexer::SEA_WS;
    }

    private function isVoidTag($name) {
        $voidTags = 'area base br col embed hr img input '
                  . 'keygen link meta param source track wbr';

        return in_array($name, explode(' ', $voidTags));
    }

    private function isHtmlElement() {
        if ($this->ltt() === HtmlLexer::TAG_OPEN
            && $this->ltt(2) === HtmlLexer::TAG_NAME) {

            return true;
        }

        return $this->ltt() === HtmlLexer::SCRIPT;
    }

    private function isHtmlElements() {
        return $this->isHtmlMisc() || $this->isHtmlElement();
    }

    private function optionalElement($tokenType) {
        if ($this->ltt() === $tokenType) {
            $this->actions->staticElementAction($this->lt());
            $this->consume();
        }
    }

    private function optionalTagSpace() {
        if ($this->ltt() === HtmlLexer::TAG_WS) {
            $this->consume();
        }
    }

    public function consume() {
        // printf('%s' . PHP_EOL, $this->ltt());

        $this->consumedPos += strlen($this->lt()->text);
        $this->p++;
    }
}


}
namespace tplLib {


abstract class AbstractNode {

    protected $name;

    protected $children = [];

    public function __construct($name) {
        $this->name = $name;
    }

    public abstract function render($scope);

    public function getTagName() {
        return $this->name;
    }

    public function getTokenContents() {
        return $this->name->getContents();
    }

    public function getChildren() {
        return $this->children;
    }

    public function addChild($child) {
        $this->children[] = $child;
    }

    public function addChildren($children) {
        $this->children = array_merge($this->children, $children);
    }

    public function removeChild($child) {
        $predicate = function ($each) use ($child) {
            return $each !== $child;
        };

        $this->children = array_values(array_filter($this->children, $predicate));
    }

    public function insertBefore($new_node, $old_node) {
        $tmp = [];
        foreach ($this->children as $current) {
            if ($current === $old_node) {
                $tmp[] = $new_node;
            }

            $tmp[] = $current;
        }

        $this->children = $tmp;
    }
}

}
namespace tplLib {



class RootNode extends AbstractNode {

    public function __construct() {
        parent::__construct(null);
    }

    public function render($scope) {
        $string = '';
        foreach ($this->children as $child) {
            $string .= $child->render($scope);
        }
        return $string;
    }

}

}
namespace tplLib {



class TagNode extends AbstractNode {

    protected $attributes;
    protected $isVoidTag;
    protected $hasSlashClose;

    public function __construct($name, $attributes) {
        parent::__construct($name);
        $this->attributes = $attributes;
    }

    public function makeVoid() {
        $this->isVoidTag = true;
    }

    public function addSlashClose() {
        if (!$this->isVoidTag) {
            throw new \RuntimeException('must be void tag');
        }

        $this->hasSlashClose = true;
    }

    public function render($scope) {
        return $this->isVoidTag
            ? $this->renderVoidTag($scope)
            : $this->renderBodyTag($scope);
    }

    public function renderVoidTag($scope) {
        $close = $this->hasSlashClose ? '/' : '';

        return sprintf('<%s%s%s>',
            $this->name, $this->attributeString($scope), $close);
    }

    public function renderBodyTag($scope) {

        $contents = $this->renderContents($scope);

        if ($this->name === 'tpl') {
            return $contents;
        }

        return sprintf('<%1$s%2$s>%3$s</%1$s>',
            $this->name, $this->attributeString($scope), $contents);
    }

    private function renderContents($scope) {
        $contents = '';
        $isTrim = $this->hasAttribute('tpl-trim-contents');

        foreach ($this->children as $index => $child) {

            if ($isTrim && $child instanceof WsNode) {
                continue;
            }

            $contents .= $child->render($scope);
        }

        return $contents;
    }

    protected function attributeString($scope) {
        $result = '';

        if ($this->hasAttribute('tpl-checked')) {
            if ($scope->evaluate($this->getExpression('tpl-checked'))) {
                $result .= ' checked="checked"';
            }
        }

        if ($this->hasAttribute('tpl-selected')) {
            if ($scope->evaluate($this->getExpression('tpl-selected'))) {
                $result .= ' selected="selected"';
            }
        }

        $attributesToSkip = [];
        if ($this->hasAttribute('tpl-class')) {
            $parts = preg_split('/\s+if\s+/', $this->getExpression('tpl-class'));

            if (count($parts) !== 2) {
                throw new \RuntimeException(
                    "invalid expression for tpl-class");
            }

            $cssClasses = [];
            if ($this->hasAttribute("class")) {
                $cssClasses[] = $this->getExpression('class');
                $attributesToSkip[] = 'class';
            }

            $cssClass = trim($parts[0]);
            $expression = trim($parts[1]);

            if ($scope->evaluate($expression)) {
                $cssClasses[] = $cssClass;
            }

            if (!empty($cssClasses)) {
                $result .= sprintf(' class="%s"', join(' ', $cssClasses));
            }
        }

        foreach ($this->attributes as $key => $value) {
            if (strpos($key, 'tpl-') === 0) {
                continue;
            }
            if (in_array($key, $attributesToSkip)) {
                continue;
            }

            $result .= $this->formatAttribute($key,
                $scope->replaceCurlyExpression($value));
        }

        return $result;
    }

    private function hasAttribute($name) {
        foreach ($this->attributes as $key => $value) {
            if ($key === $name) {
                return true;
            }
        }

        return false;
    }

    private function formatAttribute($name, $value) {
        return $value === null
            ? sprintf(' %s', $name)
            : sprintf(' %s=%s', $name, $value);
    }

    protected function getExpression($attributeName) {
        $value = $this->attributes[$attributeName];

        $value = preg_replace("/^['\"]/", '', $value);
        $value = preg_replace("/['\"]$/", '', $value);

        return $value;
    }
}

}
namespace tplLib {


class TextNode extends AbstractNode {

    private $text;

    public function __construct($text) {
        parent::__construct('');

        $this->text = $text;
    }

    public function render($scope) {
        return $scope->replaceCurlyExpression($this->text);
    }

}

}
namespace tplLib {



class MiscNode extends AbstractNode {

    private $text;

    public function __construct($text) {
        parent::__construct('');

        $this->text = $text;
    }

    public function render($scope) {
        return $this->text;
    }

}

}
namespace tplLib {


class WsNode extends AbstractNode {

    private $text;

    public function __construct($text) {
        parent::__construct('');

        $this->text = $text;
    }

    public function render($scope) {
        return $this->text;
    }

}

}
namespace tplLib {



class IfNode extends TagNode {

    public function render($scope) {
        if (!$scope->evaluate($this->getExpression('tpl-if'))) {
            return '';
        }

        return parent::render($scope);
    }
}

}
namespace tplLib {



class ForNode extends TagNode {

    public function render($scope) {

        $parts = preg_split('/\s+as\s+/', $this->getExpression('tpl-foreach'));
        $expression = trim($parts[0]);
        $variableName = trim($parts[1]);
        $variableName = substr($variableName, 1);

        $list = $scope->evaluate($expression);

        $list = $list === null ? [] : $list;

        $result = '';
        $index = 0;
        foreach ($list as $each) {

            $scope->addLayer([
                'index' => $index,
                'first' => $index === 0,
                'last' => $index === count($list) - 1,
                $variableName => $each
            ]);

            $result .= parent::render($scope);

            $scope->removeLayer();

            $index++;
        }

        return $result;
    }
}

}
namespace tplLib {


function loadContents($filePath) {
    if (is_dir($filePath)) {
        throw new \RuntimeException("$filePath is directory");
    }

    $contents = file_get_contents($filePath);

    if ($contents === false) {
        throw new \RuntimeException("can't read file: $filePath");
    }

    return $contents;
}
}
namespace tplLib {


use http\Exception\RuntimeException;


class IncludeNode extends TagNode {

    public function render($scope) {

        $path = $scope->replaceCurlyExpression($this->getExpression('tpl-include'));

        if (strlen($path) === 0) {
            throw new RuntimeException("tpl-include file path is missing");
        }

        $path = $scope->mainTemplatePath . '/' . $path;

        $html = loadContents($path);

        $tree = $this->buildTree($html);

        $this->addChild($tree);

        return parent::render($scope);
    }

    private function buildTree($html) {
        $tokens = (new HtmlLexer($html))->tokenize();

        $builder = new TreeBuilderActions();

        (new HtmlParser($tokens, $builder))->parseFragment();

        return $builder->getResult();
    }
}

}
namespace tplLib {



class TreeBuilderActions {

    private $stack;

    public function __construct() {
        $this->stack = [];
        $this->stack[] = new RootNode();
    }

    public function getResult() {
        list ($first) = $this->stack;
        return $first;
    }

    private function currentNode() {
        return $this->stack[count($this->stack) - 1];
    }

    public function tagStartAction($tagName, $attributes) {
        $node = $this->createTag($tagName, $attributes);

        $this->currentNode()->addChild($node);

        $this->stack[] = $node;
    }

    private function createTag($tagName, $attributes) {
        if (isset($attributes['tpl-if'])) {
            return new IfNode($tagName, $attributes);
        } else if (isset($attributes['tpl-foreach'])) {
            return new ForNode($tagName, $attributes);
        } else if (isset($attributes['tpl-include'])) {
            return new IncludeNode($tagName, $attributes);
        } else {
            return new TagNode($tagName, $attributes);
        }
    }

    public function tagEndAction($tagName) {
        array_pop($this->stack);
    }

    public function voidTagAction($tagName, $attributes, $hasSlashClose) {
        $node = $this->createTag($tagName, $attributes);

        $node->makeVoid();

        if ($hasSlashClose) {
            $node->addSlashClose();
        }

        $this->currentNode()->addChild($node);
    }

    public function staticElementAction($token) {

        if ($token->type === HtmlLexer::HTML_TEXT) {
            $wholeText = $token->text;
            $trimmed = rtrim($wholeText);
            $whiteSpace = substr($wholeText, strlen($trimmed));

            $this->currentNode()->addChild(new TextNode($trimmed));
            if (!empty($whiteSpace)) {
                $this->currentNode()->addChild(new WsNode($whiteSpace));
            }

        } else if ($token->type === HtmlLexer::SEA_WS) {
            $this->currentNode()->addChild(new WsNode($token->text));
        } else {
            $this->currentNode()->addChild(new MiscNode($token->text));
        }
    }
}


}
namespace tplLib {



class FileParser {

    private $filePath;
    private $input;

    public function __construct($filePath) {
        $this->filePath = $filePath;
        $this->input = loadContents($this->filePath);
    }

    public function parse() {
        try {
            $tokens = (new HtmlLexer($this->input))->tokenize();

            $builder = new TreeBuilderActions();

            (new HtmlParser($tokens, $builder))->parse();

        } catch (\Exception $e) {
            throw $this->error($e);
        }

        return $builder->getResult();
    }

    private function error($e) {
        $message = sprintf("%s \nat %s:%s\n",
            $e->message,
            realpath($this->filePath),
            $this->locationString($e->pos));

        return new \RuntimeException($message);
    }

    private function locationString($pos) {
        $textParsed = substr($this->input, 0, $pos);
        $lines = explode("\n", $textParsed);
        $lineNr = count($lines);
        $colNr = strlen($lines[$lineNr - 1]) + 1; // +1: starts from 1

        return sprintf('%s:%s', $lineNr, $colNr);
    }


}


}
namespace tplLib {


class Scope {
    public $mainTemplatePath;

    private $layers = [];
    private $translations;

    public function __construct($data = [],
                                $translations = [],
                                $mainTemplatePath = null) {

        $this->translations = $translations;

        $this->addLayer($data);

        $this->mainTemplatePath = $mainTemplatePath;
    }

    public function replaceCurlyExpression($text) {
        return preg_replace_callback(
            '|{{(.+?)}}|im',
            function ($matches) {
                $result = $this->evaluate(trim($matches[1]));
                return htmlspecialchars($result, ENT_NOQUOTES);
            },
            $text);
    }

    public function evaluate($expression) {
        $isError = false;

        $handler = function ($errno, $errstr, $errfile, $errline)
        use (&$isError) {
            $isError = $errno !== E_NOTICE;
        };

        $data = $this->getData();

        $oldHandler = set_error_handler($handler);

        if ($this->isTranslation($expression)) {
            return isset($this->translations[$expression]) ?
                $this->translations[$expression] : '';
        }

        try {
            $result = $this->evaluateSub($expression, $data);
        } catch (\Error $error) {
            $this->throwEvaluateException($expression);
        }

        set_error_handler($oldHandler);

        if ($isError) {
            $this->throwEvaluateException($expression);
        }

        return $result;
    }

    private function throwEvaluateException($expression) {
        throw new \RuntimeException("error evaluating: '$expression'");
    }

    private function isTranslation($expression) {
        return preg_match('/^[_a-zA-Z][-_0-9a-zA-Z]*$/', $expression);
    }

    private function evaluateSub($expression_8slSL29x, $data_8slSL29x) {
        foreach ($data_8slSL29x as $key_8slSL29x => $value_8slSL29x) {
            ${ $key_8slSL29x } = $value_8slSL29x;
        }

        return @eval('return ' . $expression_8slSL29x . ';');
    }

    public function addLayer($data = []) {
        $this->layers[] = $data;
    }

    public function removeLayer() {
        if (count($this->layers) == 1) {
            throw new \RuntimeException("can't remove last layer");
        }

        array_pop($this->layers);
    }

    public function getEntry($key) {
        foreach (array_reverse($this->layers) as $layer) {
            if (isset($layer[$key])) {
                return $layer[$key];
            }
        }

        return null;
    }

    public function getData() {
        $data = [];
        foreach ($this->layers as $layer) {
            foreach ($layer as $key => $value) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public function __toString() {
        return '' . print_r($this->layers, true);
    }
}

}
namespace  {


function renderTemplate($templatePath, $data = [], $translations = []) {

    try {
        $tree = (new tplLib\FileParser($templatePath))->parse();

        return $tree->render(new tplLib\Scope($data, $translations,
            realpath(dirname($templatePath))));

    } catch (Exception $e) {
        return sprintf('<pre>%s%s%s</pre>', PHP_EOL, $e->getMessage(), PHP_EOL);
    }
}

}
