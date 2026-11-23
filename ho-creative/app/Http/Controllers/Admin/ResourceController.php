<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ResourceRegistry;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResourceController extends Controller
{
    protected function def(string $resource): array
    {
        $def = ResourceRegistry::get($resource);
        $this->authorize($def['perm']);

        return $def;
    }

    public function index(Request $request, string $resource)
    {
        $def = $this->def($resource);
        $q = $def['model']::query();
        [$col, $dir] = $def['order'] ?? ['id', 'desc'];
        $q->orderBy($col, $dir);
        if ($s = trim((string) $request->query('q'))) {
            $cols = array_keys(array_filter($def['fields'], fn ($f) => in_array($f[0], ['text', 'tr'])));
            $q->where(function ($w) use ($cols, $s) {
                foreach ($cols as $c) {
                    $w->orWhere($c, 'like', '%'.$s.'%');
                }
            });
        }
        $rows = $q->paginate(40)->withQueryString();

        return view('admin.resource.index', compact('def', 'rows'));
    }

    public function create(string $resource)
    {
        $def = $this->def($resource);
        $model = new $def['model'];
        foreach ($def['fields'] as $name => $f) {
            if ($f[0] === 'checkbox') {
                $model->{$name} = true;
            }
        }

        return view('admin.resource.form', compact('def', 'model'));
    }

    public function edit(string $resource, int $id)
    {
        $def = $this->def($resource);
        $model = $def['model']::findOrFail($id);

        return view('admin.resource.form', compact('def', 'model'));
    }

    public function store(Request $request, string $resource)
    {
        $def = $this->def($resource);
        $model = new $def['model'];
        $model->fill($this->data($request, $def, null))->save();
        if (! empty($def['audit'])) {
            AuditLogger::log($resource.'.created', $model, null, $model->toArray());
        }
        $this->afterSave($resource);

        return redirect()->route('admin.r.index', $resource)->with('ok', 'Enregistré.');
    }

    public function update(Request $request, string $resource, int $id)
    {
        $def = $this->def($resource);
        $model = $def['model']::findOrFail($id);
        $old = $model->toArray();
        $model->fill($this->data($request, $def, $id))->save();
        if (! empty($def['audit'])) {
            AuditLogger::log($resource.'.updated', $model, array_intersect_key($old, $model->getChanges()), $model->getChanges());
        }
        $this->afterSave($resource);

        return redirect()->route('admin.r.index', $resource)->with('ok', 'Modifications enregistrées.');
    }

    public function destroy(string $resource, int $id)
    {
        $def = $this->def($resource);
        $model = $def['model']::findOrFail($id);
        try {
            $model->delete();
        } catch (QueryException) {
            return back()->with('err', 'Suppression impossible : cet élément est utilisé ailleurs. Désactivez-le plutôt.');
        }
        if (! empty($def['audit'])) {
            AuditLogger::log($resource.'.deleted', null, $model->toArray(), null);
        }

        return back()->with('ok', 'Supprimé.');
    }

    public function export(string $resource)
    {
        $def = $this->def($resource);
        $rows = $def['model']::query()->orderBy('id')->get();

        return response()->streamDownload(function () use ($def, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($def['columns']), ';');
            foreach ($rows as $m) {
                fputcsv($out, array_map(fn ($c) => strip_tags((string) $this->cell($def, $m, $c)), array_keys($def['columns'])), ';');
            }
            fclose($out);
        }, $resource.'-'.date('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function cell(array $def, $m, string $c): string
    {
        if (isset($def['display'][$c])) {
            return (string) $def['display'][$c]($m);
        }
        $v = $m->{$c};

        return is_array($v) ? tr($v) : (string) $v;
    }

    protected function data(Request $request, array $def, ?int $id): array
    {
        $rules = [];
        foreach ($def['fields'] as $name => $f) {
            $r = str_replace('{id}', $id ? (string) $id : 'NULL', $f[2] ?? 'nullable');
            match ($f[0]) {
                'tr', 'tr_area' => [$rules[$name.'.fr'] = $r === 'required' ? 'required|string|max:20000' : 'nullable|string|max:20000', $rules[$name.'.ar'] = 'nullable|string|max:20000'],
                'checkbox' => $rules[$name] = 'boolean',
                'image' => $rules[$name.'_file'] = 'nullable|image|max:4096',
                default => $rules[$name] = $r,
            };
        }
        $v = $request->validate($rules);
        $data = [];
        foreach ($def['fields'] as $name => $f) {
            $data[$name] = match ($f[0]) {
                'tr', 'tr_area' => ['fr' => $v[$name]['fr'] ?? '', 'ar' => $v[$name]['ar'] ?? ''],
                'checkbox' => $request->boolean($name),
                'list' => array_values(array_filter(array_map('trim', explode(',', (string) ($v[$name] ?? ''))), 'strlen')),
                'json' => $this->json($v[$name] ?? null, $name),
                'image' => $request->hasFile($name.'_file') ? '/storage/'.$request->file($name.'_file')->store('uploads', 'public') : $request->input($name.'_current'),
                default => $v[$name] ?? null,
            };
            if ($f[0] === 'list' && $name === 'product_ids') {
                $data[$name] = $data[$name] ? array_map('intval', $data[$name]) : null;
            }
        }

        return $data;
    }

    protected function json(?string $raw, string $field): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $d = json_decode($raw, true);
        if (! is_array($d)) {
            throw ValidationException::withMessages([$field => 'JSON invalide : '.json_last_error_msg()]);
        }

        return $d;
    }

    protected function afterSave(string $resource): void
    {
        if ($resource === 'categories') {
            cache()->forget('hoc.settings');
        }
    }
}
