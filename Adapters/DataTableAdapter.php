<?php

namespace Src\Framework\Adapters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Src\Framework\Helper;

class DataTableAdapter
{
    const WHERE_CONDITION = 'where';
    const HAVING_CONDITION = 'having';

    const SEARCH_TEXT = 'text';

    const EQ_OPERATOR = '=';
    const GT_OPERATOR = '>';
    const LT_OPERATOR = '<';
    const IS_NULL = 'null';
    const IS_NOT_NULL = 'not_null';
    const LIKE_STRICT_OPERATOR = 'like';
    const LIKE_LOOSE_OPERATOR = 'like_loose';
    const BETWEEN_OPERATOR = 'between';

    const OPERATOR_ARRAY = [
        self::LIKE_STRICT_OPERATOR => 'LIKE',
        self::LIKE_LOOSE_OPERATOR  => 'LIKE',
        self::EQ_OPERATOR          => '=',
        self::BETWEEN_OPERATOR     => 'BETWEEN',
    ];

    const DATE_NAME_LIST = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public $columns;
    public $searchValue;
    public $start;
    public $length;
    public $draw;
    public $orderByColumns;
    public $modelColumns;
    public $recordsTotal;
    public $recordsFiltered;

    protected $model;

    /**
     * DataTableAdapter constructor.
     *
     * @param       $ormModel | Eloquent model object
     * @param array $fields | DataTable query fields array received from the plugin
     * @param array $modelColumns | see $this->exampleModelColumns() function
     * @param array $filters
     */
    public function __construct($ormModel, array $fields, array $modelColumns, array $filters = [])
    {
        $this->model = $ormModel;
        $this->modelColumns = $modelColumns;
        $this->columns = $fields['columns'];
        $this->searchValue = trim($fields['search']['value']);
        $this->start = $fields['start'];
        $this->length = $fields['length'];
        $this->filters = $filters;
        $this->order = $fields['order'];
        $this->draw = $fields['draw'];
        $this->orderByColumns = $fields['order'];
        $this->recordsTotal = $this->model->get()->count();

        $this->setConditions();

    }

    /**
     * Runs the query and returns the expected parameters for the dataTable plugin
     *
     * @return array
     */
    public function render($withOffset = true)
    {

        $this->setRecordsFiltered();

        if ($withOffset) {
            $this->setOffset();
        }

        $results = $this->model->get();

        return [
            'data'            => $results,
            'draw'            => $this->draw,
//            'start'           => $this->start,
//            'length'          => $this->length,
//            'search'          => ['value' => $this->searchValue, 'regex' => false],
            'recordsTotal'    => $this->recordsTotal,
            'recordsFiltered' => $this->recordsFiltered,
        ];
    }

    /**
     * Matches the dataTables array data with the associated user defined array for
     * building the model where/having/sort conditions
     */
    protected function setConditions()
    {

        $this->model = $this->model->where(function ($q) {
            foreach ($this->columns as $key => $column) {

                $columnName = $column['data'];
                $modelColumn = $this->modelColumns[$columnName] ?? null;

                if ($modelColumn) {
                    $finalColumn = [
                        'condition'    => $modelColumn['condition'] ?? self::WHERE_CONDITION,
                        'operator'     => $modelColumn['operator'] ?? self::EQ_OPERATOR,
                        'search_type'  => $modelColumn['search_type'] ?? self::SEARCH_TEXT,
                        'model_column' => $modelColumn['model_column'],
                    ];

                    //Sets order by columns
                    $this->setOrder($key, $finalColumn);

                    if (!empty($column['searchable']) && $column['searchable'] == 'true' && !empty($this->searchValue)) {

                        $date = $this->getDate();

                        $searchValue = $this->filterSearchValue($finalColumn['operator']);
                        $operator = $this->getMatchingOperator($finalColumn['operator']);

                        //Check the type of condition
                        switch ($finalColumn['condition']) {

                            case self::WHERE_CONDITION:
                                //Verify is the column name is in the name of date accepted array
                                if ($date && in_array($columnName, self::DATE_NAME_LIST)) {
                                    $q->orWhereBetween($finalColumn['model_column'], $date);
                                } elseif (!$date) {
                                    $q->orWhere($finalColumn['model_column'], $operator,
                                        $searchValue);
                                }
                                break;

                            case self::HAVING_CONDITION:

                                $q->having($finalColumn['model_column'], $operator, $searchValue);

                                break;
                        }

                    }

                }
            }

            if (!empty($this->filters)) {

                foreach ($this->filters as $key => $filter) {
                    $condition = $filter['condition'] ?? self::WHERE_CONDITION;

                    //Check the type of condition
                    if ($condition === self::WHERE_CONDITION) {

                        switch ($filter['operator']) {
                            case DataTableAdapter::BETWEEN_OPERATOR:
                                $q->whereBetween($filter['column'], $filter['value']);
                                break;
                            case DataTableAdapter::IS_NULL:
                                $q->whereNull($filter['column']);
                                break;

                            case DataTableAdapter::IS_NOT_NULL:
                                $q->whereNotNull($filter['column']);
                                break;

                            case DataTableAdapter::LIKE_LOOSE_OPERATOR:
                                $value = '%' . $filter['value'] . '%';
                                $q->where($filter['column'], 'LIKE', $value);
                                break;
                            default:
                                $q->where($filter['column'], $filter['value']);
                                break;
                        }

                        unset($this->filters[$key]);
                    }
                }
            }

            return $q;
        });

        //adding having conditions
        foreach ($this->filters as $key => $filter) {

            if (isset($filter['condition']) && $filter['condition'] === self::HAVING_CONDITION) {
                $this->model = $this->model->having($filter['column'], $filter['operator'] ?? self::EQ_OPERATOR,
                    $filter['value']);
            }

            unset($this->filters[$key]);
        }
    }

    /**
     * Add string manipulation based on operator type
     *
     * @param $value
     * @param $operator
     *
     * @return string
     */
    protected function filterSearchValue($operator)
    {
        $value = trim($this->searchValue);

        switch ($operator) {
            case self::LIKE_LOOSE_OPERATOR:
                $value = "%$value%";
                break;
        }

        return $value;
    }

    /**
     * Current column key and
     *
     * @param $key
     * @param $modelColumn
     */
    protected function setOrder($key, $modelColumn)
    {
        if (count($this->orderByColumns)) {
            //Match sorting columns
            foreach ($this->orderByColumns as $orderColumn) {
                if ($key == $orderColumn['column']) {
                    $this->model = $this->model->orderBy($modelColumn['model_column'], $orderColumn['dir']);
                }
            }
        }
    }

    /**
     * @param string $operator
     *
     * @return mixed
     */
    protected function getMatchingOperator($operator = self::EQ_OPERATOR)
    {
        return self::OPERATOR_ARRAY[$operator];
    }

    /**
     * Sets the query offset and limit. Used for pagination.
     */
    protected function setOffset()
    {
        $this->model = $this->model->offset($this->start)->limit($this->length);
    }

    /**
     * Validates the search string if its a valid date
     * and builds a query with start-end of day
     *
     * @return array|null
     */
    protected function getDate()
    {
        $date = Helper::validateAndCleanDate($this->searchValue);

        if ($date) {
            $start = Carbon::parse($date)->startOfDay();
            $end = Carbon::parse($date)->endOfDay();

            return [$start, $end];
        }

        return null;
    }

    /**
     *
     */
    protected function setRecordsFiltered()
    {
        $model = $this->model;

        $this->recordsFiltered = $model->get()->count();
    }


    /**
     * @return array
     */
    private function exampleModelColumns()
    {
        return [
            'member'          => [
                'operator'     => DataTableAdapter::LIKE_LOOSE_OPERATOR,   //Optional default =
                'model_column' => \DB::raw('CONCAT(members.first_name, " ", members.last_name)'),   //Required
            ],
            'owner'           => [
                'operator'     => DataTableAdapter::LIKE_LOOSE_OPERATOR,
                'model_column' => \DB::raw('CONCAT(coach.first_name, " ", coach.last_name)'),
            ],
            'campaign'        => ['model_column' => 'campaigns.name'],
            'master_template' => ['model_column' => 'master_templates.name'],
            'start_date'      => ['model_column' => 'campaigns.start_at'],
            'due_date'        => ['model_column' => 'campaigns.end_at'],
            'status'          => [
                'operator'     => DataTableAdapter::LIKE_LOOSE_OPERATOR,
                'model_column' => \DB::raw('
                    IF(observation.is_finished, "' .
                    trans('label.closed') . '", "' .
                    trans('label.open') . '")
                '),
            ],
        ];
    }


    /**
     * Test data
     */
    public function setTestData()
    {
        $this->model = new \Src\Users\User();
        $this->searchValue = 'Robert';
        $this->start = 0;
        $this->length = 10;
        $this->draw = 1;

        $this->model = $this->model->selectRaw('
            id,
            CONCAT(first_name, " ", last_name) AS name,
            email,
            is_user,
            is_external,
            job_role_id,
            is_user,
            is_external,
            is_forgotten,
            created_at,
            deleted_at
        ');

        $this->orderByColumns = [0 => ['dir' => 'asc', 'column' => 1]];

        $this->modelColumns = [
            'name'  => [
                'operator'     => 'like',   //Optional default =
                'condition'    => 'having', //Optional default where
                'model_column' => 'name',   //Required
            ],
            'email' => [
                'operator'     => '=',
                'model_column' => 'users.email',
            ],
        ];

        $this->columns = [
            0  => [
                "data"       => "name",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            1  => [
                "data"       => "email",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            2  => [
                "data"       => "is_user",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            3  => [
                "data"       => "is_external",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            4  => [
                "data"       => "job_role",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            5  => [
                "data"       => "team",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            6  => [
                "data"       => "department",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            7  => [
                "data"       => "segment",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            8  => [
                "data"       => "reports_to",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            9  => [
                "data"       => "created_at",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            10 => [
                "data"       => "status",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
            11 => [
                "data"       => "actions",
                "name"       => null,
                "searchable" => "true",
                "orderable"  => "true",
                "search"     => [
                    "value" => null,
                    "regex" => "false",
                ],
            ],
        ];
    }


}