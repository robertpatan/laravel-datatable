# Example implementation

### In a controller
```
$table = new DataTableFactory();
$table->setTableHeader($this->getTableHeader());
$table->setHtmlID('id_of_html_datatable');
$table->setAjaxUrl(route('data endpoint'));

//inject $table in blade view
```

### getTableHeader
```
protected function getTableHeader($withActions = true)
    {
        $header = [
            trans('label.name'),
            trans('label.email'),
            trans('label.status'),
        ];

        if ($withActions) {
            $header[] = trans_choice('label.actions', 2);
        }

        return $header;
    }
```

### In Blade
```
@include('components.datatable', ['table' => $table]) 
```


### Repository/Datatable ajax route usage
```
/**
* $field - the query string params created by datatable library which is used
* for search & sorting
*/
public function listUsers($fields = [], $withOffset = true)
    {
        $datatableModel = $this->model
            ->with(['unit', 'roles'])
            ->leftJoin('users_unit_info', 'users_unit_info.id', '=', 'users.id')
            ->select($this->getDataTableSelect());

        $dtData = [];

        try {

            $table = new DataTableAdapter(
                $datatableModel, $fields,
                $this->getDataTableModelColumns(),
                $this->setColumnFilter($fields['filters'])
            );

            $dtData = $table->render($withOffset);

            $dtData['data']->transform(function ($item) {

                $row = [
                    'name'       => $item->name,
                    'email'      => $item->email
                ]
            });
        } catch (\Exception $e) {

            $dtData['error'] = $e->getMessage();

            \Log::error($e);

        }

        return $dtData;
    }
```
