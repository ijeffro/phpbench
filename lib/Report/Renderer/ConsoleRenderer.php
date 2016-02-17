<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Report\Renderer;

use PhpBench\Console\OutputAwareInterface;
use PhpBench\Dom\Document;
use PhpBench\Dom\Element;
use PhpBench\Formatter\Formatter;
use PhpBench\Registry\Config;
use PhpBench\Report\RendererInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleRenderer implements RendererInterface, OutputAwareInterface
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var Formatter
     */
    private $formatter;

    public function __construct(Formatter $formatter)
    {
        $this->formatter = $formatter;
        $formatter->classesFromFile(__DIR__ . '/classes/main.json');
    }

    /**
     * {@inheritdoc}
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        $this->configureFormatters($output->getFormatter());
    }

    /**
     * Render the table.
     *
     * @param mixed $tableDom
     * @param mixed $config
     */
    public function render(Document $reportDom, Config $config)
    {
        foreach ($reportDom->firstChild->query('./report') as $reportEl) {
            $title = $reportEl->getAttribute('title');

            if ($title) {
                $this->output->writeln(sprintf('<title>%s</title>', $title));
                $this->output->writeln(sprintf('<title>%s</title>', str_repeat('=', strlen($title))));
                $this->output->write(PHP_EOL);
            }

            foreach ($reportEl->query('./description') as $descriptionEl) {
                $this->output->writeln(sprintf('<description>%s</description>', $descriptionEl->nodeValue));
                $this->output->writeln('');
            }

            foreach ($reportEl->query('.//table') as $tableEl) {
                $this->output->writeln(sprintf('<subtitle>%s</subtitle>', $tableEl->getAttribute('title')));
                $this->renderTableElement($tableEl, $config);
            }
        }
    }

    protected function renderTableElement(Element $tableEl, $config)
    {
        $rows = array();
        $row = null;

        foreach ($tableEl->query('.//row') as $rowEl) {
            $row = array();
            $formatterParams = array();

            foreach ($rowEl->query('./formatter-param') as $paramEl) {
                $formatterParams[$paramEl->getAttribute('name')] = $paramEl->nodeValue;
            }

            foreach ($rowEl->query('.//cell') as $cellEl) {
                $colName = $cellEl->getAttribute('name');
                $value = $cellEl->nodeValue;

                if ('' !== $value && $cellEl->hasAttribute('class')) {
                    $classes = explode(' ', $cellEl->getAttribute('class'));
                    $value = $this->formatter->applyClasses($classes, $value, $formatterParams);
                }

                $row[$colName] = $value;
            }

            $rows[] = $row;
        }

        $table = $this->createTable();

        // style only supported in Symfony > 2.4
        if (method_exists($table, 'setStyle')) {
            $table->setStyle($config['table_style']);
        }

        $table->setHeaders(array_keys($row ?: array()));
        $table->setRows($rows);
        $this->renderTable($table);
        $this->output->writeln('');
    }

    /**
     * Render the table. For Symfony 2.4 support.
     *
     * @param mixed $table
     */
    private function renderTable($table)
    {
        if (class_exists('Symfony\Component\Console\Helper\Table')) {
            $table->render();

            return;
        }
        $table->render($this->output);
    }

    /**
     * Create the table class. For Symfony 2.4 support.
     *
     * @return object
     */
    protected function createTable()
    {
        if (class_exists('Symfony\Component\Console\Helper\Table')) {
            return new Table($this->output);
        }

        return new \Symfony\Component\Console\Helper\TableHelper();
    }

    /**
     * Adds some output formatters.
     *
     * @param OutputFormatterInterface
     */
    private function configureFormatters(OutputFormatterInterface $formatter)
    {
        $formatter->setStyle(
            'title', new OutputFormatterStyle('white', null, array('bold'))
        );
        $formatter->setStyle(
            'subtitle', new OutputFormatterStyle('white', null, array())
        );
        $formatter->setStyle(
            'description', new OutputFormatterStyle(null, null, array())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultConfig()
    {
        return array(
            'table_style' => 'default',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema()
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'table_style' => array(
                    'title' => 'Style of the table',
                    'enum' => array('default', 'borderless', 'compact', 'symfony-style-guide'),
                ),
            ),
            'additionalProperties' => false,
        );
    }
}
