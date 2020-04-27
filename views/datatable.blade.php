<table id="{{ $table->getHtmlID() }}" class="table table-striped table-bordered" cellspacing="0" width="100%"
       data-url="{{ $table->getAjaxUrl() }}">
    <thead>
    <tr>
        @forelse($table->getHeaders() as $head)
            <th class="">{{ $head }}</th>
        @empty
        @endforelse
    </tr>
    </thead>
</table>