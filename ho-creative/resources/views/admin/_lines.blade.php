{{-- Shared line editor for quotes and staff orders. $existing = array of rows --}}
<div data-rows>
  <div data-rows-list>
    @foreach($existing as $i => $row)
      @include('admin._line', ['i' => $i, 'row' => $row])
    @endforeach
  </div>
  <template>@include('admin._line', ['i' => '__i__', 'row' => []])</template>
  <button type="button" class="btn btn-sm btn-ghost" data-rows-add>+ Ajouter une ligne</button>
</div>
