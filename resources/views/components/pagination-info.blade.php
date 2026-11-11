<div class="row mt-3 justify-center">
    <div class="col-md-6">
        @if ($paginator->count())
            Showing {{ $paginator->firstItem() }}
            to {{ $paginator->lastItem() }}
            of {{ $paginator->total() }} results
        @endif
    </div>
    
    <div class="col-md-6 d-flex justify-content-end">
        {{ $paginator->links() }}
    </div>
</div>