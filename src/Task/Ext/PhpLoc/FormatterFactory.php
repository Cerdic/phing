<?php

/**
 * $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://phing.info>.
 */

namespace Phing\Task\Ext\PhpLoc;

use Phing\Exception\BuildException;

/**
 * A wrapper for the implementations of PHPUnit2ResultFormatter.
 *
 * @author Michiel Rook <mrook@php.net>
 * @version $Id$
 * @package phing.tasks.ext.phploc
 */
class FormatterFactory
{
    /**
     * Returns formatter object
     * @param FormatterElement $formatterElement
     * @throws BuildException
     * @return AbstractFormatter
     */
    public static function createFormatter($formatterElement)
    {
        $formatter = null;
        $type = $formatterElement->getType();

        switch ($type) {
            case "xml":
                $formatter = new XmlFormatter();
                break;
            case "csv":
                $formatter = new CsvFormatter();
                break;
            case "txt":
            case "cli":
                $formatter = new TextFormatter();
                break;
            default:
                throw new BuildException("Formatter '" . $type . "' not implemented");
        }

        $formatter->setOutfile($formatterElement->getOutfile());
        $formatter->setToDir($formatterElement->getToDir());
        $formatter->setUseFile($formatterElement->getUseFile());

        return $formatter;
    }
}
