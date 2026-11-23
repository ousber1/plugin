@if(! empty($file->checks['items']))
<ul class="checks small" style="padding-inline-start:16px;margin:4px 0">
  @foreach($file->checks['items'] as $c)
    <li class="check-{{ $c['status'] }}">{{ ['ok' => '✔', 'warning' => '⚠', 'manual' => '•'][$c['status']] ?? '•' }} {{ $c['message'] }}</li>
  @endforeach
</ul>
@endif
