<?php

namespace Src\Framework\Factory;

class DataTableFactory
{
    protected $title;
    protected $htmlID;
    protected $tableHeader;
    protected $tableRows;
    protected $tableRowColumns;
    protected $ajaxUrl;


    public function __construct($title = '')
    {
        $this->setTitle($title);
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setTableHeader($header)
    {
        if(!is_array($header)) {
            throw \Exception('The constructor argument $header is not and array.');
        } else {
            $this->tableHeader = $header;
        }
    }

    public function setTableRows($rows)
    {
        $this->tableRows = $rows;
    }

    public function setRowColumns($columns)
    {
        $this->tableRowColumns = $columns;
    }

    public function setHtmlID($id)
    {
        $this->htmlID = $id;
    }

    public function setAjaxUrl($url)
    {
        $this->ajaxUrl = $url;
    }

    /**
     * GETTERS
     */
    public function getHeaders()
    {
        return $this->tableHeader;
    }

    public function getHeader($key)
    {
        return isset($this->tableHeader[$key]) ? $this->tableHeader[$key] : [];
    }

    public function getRowColumns()
    {
        return $this->tableRowColumns;
    }

    public function getTableRows()
    {
        return $this->tableRows;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getHtmlID()
    {
        return $this->htmlID;
    }

    public function getAjaxUrl()
    {
        return $this->ajaxUrl;
    }
}