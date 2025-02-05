<?php
/**
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

use DOMDocument;
use DOMElement;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExceptionWrapper;
use PHPUnit\Framework\SelfDescribing;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestFailure;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Util\Filter;
use PHPUnit\Util\Printer;
use PHPUnit\Util\Xml;
use ReflectionClass;
use ReflectionException;

/**
 * A TestListener that generates a logfile of the test execution in XML markup.
 *
 * The XML markup used is the same as the one that is used by the JUnit Ant task.
 */
class JUnit extends Printer implements TestListener
{
    /**
     * @var DOMDocument
     */
    protected $document;

    /**
     * @var DOMElement
     */
    protected $root;

    /**
     * @var bool
     */
    protected $reportUselessTests = false;

    /**
     * @var bool
     */
    protected $writeDocument = true;

    /**
     * @var DOMElement[]
     */
    protected $testSuites = [];

    /**
     * @var int[]
     */
    protected $testSuiteTests = [0];

    /**
     * @var int[]
     */
    protected $testSuiteAssertions = [0];

    /**
     * @var int[]
     */
    protected $testSuiteErrors = [0];

    /**
     * @var int[]
     */
    protected $testSuiteFailures = [0];

    /**
     * @var int[]
     */
    protected $testSuiteSkipped = [0];

    /**
     * @var int[]
     */
    protected $testSuiteTimes = [0];

    /**
     * @var int
     */
    protected $testSuiteLevel = 0;

    /**
     * @var DOMElement
     */
    protected $currentTestCase;

    /**
     * Constructor.
     *
     * @param null|mixed $out
     *
     * @throws \PHPUnit\Framework\Exception
     */
    public function __construct($out = null, bool $reportUselessTests = false)
    {
        $this->document               = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;

        $this->root = $this->document->createElement('testsuites');
        $this->document->appendChild($this->root);

        parent::__construct($out);

        $this->reportUselessTests = $reportUselessTests;
    }

    /**
     * Flush buffer and close output.
     */
    public function flush(): void
    {
        if ($this->writeDocument === true) {
            $this->write($this->getXML());
        }

        parent::flush();
    }

    /**
     * An error occurred.
     *
     * @throws \InvalidArgumentException
     */
    public function addError(Test $test, \Throwable $t, float $time): void
    {
        $this->doAddFault($test, $t, $time, 'error');
        $this->testSuiteErrors[$this->testSuiteLevel]++;
    }

    /**
     * A warning occurred.
     *
     * @throws \InvalidArgumentException
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->doAddFault($test, $e, $time, 'warning');
        $this->testSuiteFailures[$this->testSuiteLevel]++;
    }

    /**
     * A failure occurred.
     *
     * @throws \InvalidArgumentException
     */
    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->doAddFault($test, $e, $time, 'failure');
        $this->testSuiteFailures[$this->testSuiteLevel]++;
    }

    /**
     * Incomplete test.
     */
    public function addIncompleteTest(Test $test, \Throwable $t, float $time): void
    {
        $this->doAddSkipped($test);
    }

    /**
     * Risky test.
     */
    public function addRiskyTest(Test $test, \Throwable $t, float $time): void
    {
        if (!$this->reportUselessTests || $this->currentTestCase === null) {
            return;
        }

        $error = $this->document->createElement(
            'error',
            Xml::prepareString(
                "Risky Test\n" .
                Filter::getFilteredStacktrace($t)
            )
        );

        $error->setAttribute('type', \get_class($t));

        $this->currentTestCase->appendChild($error);

        $this->testSuiteErrors[$this->testSuiteLevel]++;
    }

    /**
     * Skipped test.
     */
    public function addSkippedTest(Test $test, \Throwable $t, float $time): void
    {
        $this->doAddSkipped($test);
    }

    /**
     * A testsuite started.
     */
    public function startTestSuite(TestSuite $suite): void
    {
        $testSuite = $this->document->createElement('testsuite');
        $testSuite->setAttribute('name', $suite->getName());

        if (\class_exists($suite->getName(), false)) {
            try {
                $class = new ReflectionClass($suite->getName());

                $testSuite->setAttribute('file', $class->getFileName());
            } catch (ReflectionException $e) {
            }
        }

        if ($this->testSuiteLevel > 0) {
            $this->testSuites[$this->testSuiteLevel]->appendChild($testSuite);
        } else {
            $this->root->appendChild($testSuite);
        }

        $this->testSuiteLevel++;
        $this->testSuites[$this->testSuiteLevel]          = $testSuite;
        $this->testSuiteTests[$this->testSuiteLevel]      = 0;
        $this->testSuiteAssertions[$this->testSuiteLevel] = 0;
        $this->testSuiteErrors[$this->testSuiteLevel]     = 0;
        $this->testSuiteFailures[$this->testSuiteLevel]   = 0;
        $this->testSuiteSkipped[$this->testSuiteLevel]    = 0;
        $this->testSuiteTimes[$this->testSuiteLevel]      = 0;
    }

    /**
     * A testsuite ended.
     */
    public function endTestSuite(TestSuite $suite): void
    {
        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'tests',
            $this->testSuiteTests[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'assertions',
            $this->testSuiteAssertions[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'errors',
            $this->testSuiteErrors[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'failures',
            $this->testSuiteFailures[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'skipped',
            $this->testSuiteSkipped[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'time',
            \sprintf('%F', $this->testSuiteTimes[$this->testSuiteLevel])
        );

        if ($this->testSuiteLevel > 1) {
            $this->testSuiteTests[$this->testSuiteLevel - 1] += $this->testSuiteTests[$this->testSuiteLevel];
            $this->testSuiteAssertions[$this->testSuiteLevel - 1] += $this->testSuiteAssertions[$this->testSuiteLevel];
            $this->testSuiteErrors[$this->testSuiteLevel - 1] += $this->testSuiteErrors[$this->testSuiteLevel];
            $this->testSuiteFailures[$this->testSuiteLevel - 1] += $this->testSuiteFailures[$this->testSuiteLevel];
            $this->testSuiteSkipped[$this->testSuiteLevel - 1] += $this->testSuiteSkipped[$this->testSuiteLevel];
            $this->testSuiteTimes[$this->testSuiteLevel - 1] += $this->testSuiteTimes[$this->testSuiteLevel];
        }

        $this->testSuiteLevel--;
    }

    /**
     * A test started.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws ReflectionException
     */
    public function startTest(Test $test): void
    {
        $usesDataprovider = false;

        if (\method_exists($test, 'usesDataProvider')) {
            $usesDataprovider = $test->usesDataProvider();
        }

        $testCase = $this->document->createElement('testcase');
        $testCase->setAttribute('name', $test->getName());

        $class      = new ReflectionClass($test);
        $methodName = $test->getName(!$usesDataprovider);

        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            $testCase->setAttribute('class', $class->getName());
            $testCase->setAttribute('classname', \str_replace('\\', '.', $class->getName()));
            $testCase->setAttribute('file', $class->getFileName());
            $testCase->setAttribute('line', $method->getStartLine());
        }

        $this->currentTestCase = $testCase;
    }

    /**
     * A test ended.
     */
    public function endTest(Test $test, float $time): void
    {
        $numAssertions = 0;

        if (\method_exists($test, 'getNumAssertions')) {
            $numAssertions = $test->getNumAssertions();
        }

        $this->testSuiteAssertions[$this->testSuiteLevel] += $numAssertions;

        $this->currentTestCase->setAttribute(
            'assertions',
            $numAssertions
        );

        $this->currentTestCase->setAttribute(
            'time',
            \sprintf('%F', $time)
        );

        $this->testSuites[$this->testSuiteLevel]->appendChild(
            $this->currentTestCase
        );

        $this->testSuiteTests[$this->testSuiteLevel]++;
        $this->testSuiteTimes[$this->testSuiteLevel] += $time;

        $testOutput = '';

        if (\method_exists($test, 'hasOutput') && \method_exists($test, 'getActualOutput')) {
            $testOutput = $test->hasOutput() ? $test->getActualOutput() : '';
        }

        if (!empty($testOutput)) {
            $systemOut = $this->document->createElement(
                'system-out',
                Xml::prepareString($testOutput)
            );

            $this->currentTestCase->appendChild($systemOut);
        }

        $this->currentTestCase = null;
    }

    /**
     * Returns the XML as a string.
     */
    public function getXML(): string
    {
        return $this->document->saveXML();
    }

    /**
     * Enables or disables the writing of the document
     * in flush().
     *
     * This is a "hack" needed for the integration of
     * PHPUnit with Phing.
     */
    public function setWriteDocument(bool $flag): void
    {
        if (\is_bool($flag)) {
            $this->writeDocument = $flag;
        }
    }

    /**
     * Method which generalizes addError() and addFailure()
     *
     * @throws \InvalidArgumentException
     */
    private function doAddFault(Test $test, \Throwable $t, float $time, $type): void
    {
        if ($this->currentTestCase === null) {
            return;
        }

        if ($test instanceof SelfDescribing) {
            $buffer = $test->toString() . "\n";
        } else {
            $buffer = '';
        }

        $buffer .= TestFailure::exceptionToString($t) . "\n" .
            Filter::getFilteredStacktrace($t);

        $fault = $this->document->createElement(
            $type,
            Xml::prepareString($buffer)
        );

        if ($t instanceof ExceptionWrapper) {
            $fault->setAttribute('type', $t->getClassName());
        } else {
            $fault->setAttribute('type', \get_class($t));
        }

        $this->currentTestCase->appendChild($fault);
    }

    private function doAddSkipped(Test $test): void
    {
        if ($this->currentTestCase === null) {
            return;
        }

        $skipped = $this->document->createElement('skipped');
        $this->currentTestCase->appendChild($skipped);

        $this->testSuiteSkipped[$this->testSuiteLevel]++;
    }
}
