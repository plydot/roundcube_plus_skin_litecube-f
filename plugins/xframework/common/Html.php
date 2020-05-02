<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * Copyright 2017, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

class Html
{
    /**
     * @param string $marker
     * @param string $insertString
     * @param string $html
     * @param bool $container
     * @return bool
     */
    public function insertBefore($marker, $insertString, &$html, $container = false)
    {
        if ($pos = $this->findStart($container, $marker, $html, false)) {
            $html = substr_replace($html, $insertString, $pos, 0);
            return true;
        }

        return false;
    }

    /**
     * @param string $marker
     * @param string $tagName
     * @param string $insertString
     * @param string $html
     * @param bool $container
     * @return bool
     */
    public function insertAfter($marker, $tagName, $insertString, &$html, $container = false)
    {
        if ($pos = $this->findEnd($container, $marker, $tagName, $html, false)) {
            $html = substr_replace($html, $insertString, $pos, 0);
            return true;
        }

        return false;
    }

    /**
     * @param string $marker
     * @param string $insertString
     * @param string $html
     * @param bool $container
     * @return bool
     */
    public function insertAtBeginning($marker, $insertString, &$html, $container = false)
    {
        if ($pos = $this->findStart($container, $marker, $html, true)) {
            $html = substr_replace($html, $insertString, $pos, 0);
            return true;
        }

        return false;
    }

    /**
     * @param $marker String to search for, it can be a class or id within a tag or a text within a tag. The function
     *        will search for the first tag to the left of the marker to identify the element at the end of which the
     *        text should be inserted.
     * @param $insertString String to insert before the closing tag.
     * @param $html Html code to modify.
     * @return bool True if the string has been successfully inserted, false otherwise.
     */
    public function insertAtEnd($marker, $insertString, &$html)
    {
        // find marker
        if (!($i = stripos($html, $marker))) {
            return false;
        }

        // get the html element
        if (!($i = strripos(substr($html, 0, $i), "<")) || !($j = stripos($html, " ", $i))) {
            return false;
        }

        $tag = substr($html, $i + 1, $j - $i - 1);
        $count = 0;

        do {
            if (($c = stripos($html, "</$tag>", $i)) === false) {
                return false;
            }

            if (($n = stripos($html, "<$tag ", $i)) === false) {
                $n = $c + 1;
            }

            if ($c > $n) {
                $count++;
                $i = $n + 1;
            } else {
                $count--;
                $i = $c + 1;
            }
        } while ($count);

        $html = substr_replace($html, $insertString, $i - 1, 0);

        return true;
    }

    /**
     * @param string $insertString
     * @param string $html
     */
    public function insertBeforeBodyEnd($insertString, &$html)
    {
        $html = str_replace("</body>", $insertString . "</body>", $html);
    }

    /**
     * @param string $insertString
     * @param string $html
     */
    public function insertBeforeHeadEnd($insertString, &$html)
    {
        $html = str_replace("</head>", $insertString . "</head>", $html);
    }

    /**
     * @param string $container
     * @param string $marker
     * @param string $html
     * @param string $inner
     * @return bool|int
     */
    private function findStart($container, $marker, $html, $inner)
    {
        if (!($pos = $this->findMarker($container, $marker, $html))) {
            return false;
        }

        if ($inner) {
            if (substr($marker, -1, 1) != ">") {
                $pos = strpos($html, ">", $pos);
                if ($pos) {
                    $pos++;
                }
            }
        } else {
            // if marker doesn't include the opening tag name, find the beginning of the tag
            if (strpos($marker, "<") !== 0) {
                $pos = strrpos(substr($html, 0, $pos + 1), "<");
            }
        }

        return $pos;
    }

    /**
     * @param string $container
     * @param string $marker
     * @param string $tagName
     * @param string $html
     * @param string $inner
     * @return bool|int
     */
    private function findEnd($container, $marker, $tagName, $html, $inner)
    {
        if (!($pos = $this->findMarker($container, $marker, $html))) {
            return false;
        }

        // find the closing tag
        $end = $pos;

        do {
            $innerTagStart = strpos($html, "<$tagName ", $end + 1);
            $end = strpos($html, "</$tagName>", $end + 1);
        } while ($end !== false && $innerTagStart !== false && $innerTagStart < $end);

        return $end + ($inner ? 0 : strlen("</$tagName>"));
    }

    /**
     * @param string $container
     * @param string $marker
     * @param string $html
     * @return bool|int
     */
    private function findMarker($container, $marker, $html)
    {
        $start = empty($container) ? strpos($html, "<body ") : strpos($html, $container);

        if ($start === false) {
            return false;
        }

        return strpos($html, $marker, $start);
    }
}