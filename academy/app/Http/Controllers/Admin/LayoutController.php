<?php

namespace App\Http\Controllers\Admin;

use App\Support\Audit;
use App\Support\Locales;
use App\Support\Settings;
use App\Support\SiteChrome;
use App\Support\Themes;
use Illuminate\Http\Request;

/** Header & footer builder and one-click themes. */
class LayoutController extends AdminController
{
    public function edit()
    {
        return view('admin.layout.edit', ['s' => Settings::all(), 'columnTypes' => SiteChrome::COLUMN_TYPES]);
    }

    public function update(Request $request)
    {
        $url = ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#|mailto:|tel:)/'];
        $request->validate([
            ...$this->trRules('header_cta_label', false, 40), ...$this->trRules('header_cta_message', false, 300),
            'header_cta_action' => ['required', 'in:whatsapp,url'], 'header_cta_url' => $url,
            ...$this->trRules('topbar_text', false, 200),
            ...$this->trRules('footer_cta_title', false, 120), ...$this->trRules('footer_cta_text', false, 300),
            ...$this->trRules('footer_cta_button', false, 40), 'footer_cta_url' => $url,
            ...$this->trRules('footer_about', false, 500), ...$this->trRules('footer_copyright', false, 200),
            'columns' => ['nullable', 'array', 'max:4'],
            'columns.*.type' => ['required', 'in:none,'.implode(',', array_keys(SiteChrome::COLUMN_TYPES))],
            'columns.*.title' => ['nullable', 'array'], 'columns.*.title.*' => ['nullable', 'string', 'max:60'],
            'columns.*.links' => ['nullable', 'array', 'max:12'],
            'columns.*.links.*.url' => $url, 'columns.*.links.*.label' => ['nullable', 'array'], 'columns.*.links.*.label.*' => ['nullable', 'string', 'max:60'],
            'footer_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'footer_logo_height' => ['nullable', 'integer', 'between:24,140'],
            'bottom_links' => ['nullable', 'array', 'max:6'],
            'bottom_links.*.url' => $url, 'bottom_links.*.label' => ['nullable', 'array'], 'bottom_links.*.label.*' => ['nullable', 'string', 'max:60'],
        ], ['regex' => 'يجب أن يبدأ الرابط بـ https:// أو / أو mailto: أو tel:']);

        $columns = collect($request->input('columns', []))->filter(fn ($c) => ($c['type'] ?? 'none') !== 'none')->map(fn ($c) => [
            'type' => $c['type'],
            'title' => $this->langs($c['title'] ?? []),
            'links' => $this->links($c['links'] ?? []),
        ])->values()->all();

        $values = [
            'header_cta_enabled' => $request->boolean('header_cta_enabled'),
            'header_cta_label' => $this->trInput($request, 'header_cta_label'),
            'header_cta_action' => $request->input('header_cta_action'),
            'header_cta_url' => (string) $request->input('header_cta_url'),
            'header_cta_message' => $this->trInput($request, 'header_cta_message'),
            'header_show_auth' => $request->boolean('header_show_auth'),
            'header_show_lang' => $request->boolean('header_show_lang'),
            'topbar_enabled' => $request->boolean('topbar_enabled'),
            'topbar_text' => $this->trInput($request, 'topbar_text'),
            'sticky_header' => $request->boolean('sticky_header'),
            'footer_cta_enabled' => $request->boolean('footer_cta_enabled'),
            'footer_cta_title' => $this->trInput($request, 'footer_cta_title'),
            'footer_cta_text' => $this->trInput($request, 'footer_cta_text'),
            'footer_cta_button' => $this->trInput($request, 'footer_cta_button'),
            'footer_cta_url' => (string) $request->input('footer_cta_url'),
            'footer_about' => $this->trInput($request, 'footer_about'),
            'footer_columns' => $columns,
            'footer_show_contact' => $request->boolean('footer_show_contact'),
            'footer_show_social' => $request->boolean('footer_show_social'),
            'footer_show_back_top' => $request->boolean('footer_show_back_top'),
            'footer_copyright' => $this->trInput($request, 'footer_copyright'),
            'footer_bottom_links' => $this->links($request->input('bottom_links', [])),
        ];
        $values['footer_show_logo'] = $request->boolean('footer_show_logo');
        $values['footer_logo_height'] = (int) ($request->input('footer_logo_height') ?: 44);
        if (($request->boolean('remove_footer_logo') || $request->hasFile('footer_logo')) && Settings::get('footer_logo_path')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete(Settings::get('footer_logo_path'));
            $values['footer_logo_path'] = null;
        }
        if ($request->hasFile('footer_logo')) {
            $values['footer_logo_path'] = $request->file('footer_logo')->store('branding', 'public');
        }
        Settings::set($values);
        Audit::log('settings.updated', null, ['group' => 'layout', 'keys' => array_keys($values)]);

        return back()->with('success', 'تم حفظ الهيدر والتذييل.');
    }

    public function applyTheme(string $theme)
    {
        abort_unless(array_key_exists($theme, Themes::all()), 404);
        Settings::set(Themes::settingsFor($theme));
        Audit::log('theme.applied', null, ['theme' => $theme]);

        return back()->with('success', 'تم تطبيق ثيم «'.Themes::all()[$theme]['name'].'». يمكنك تعديل الألوان أسفله.');
    }

    private function langs(array $values): array
    {
        return collect(Locales::codes())->mapWithKeys(fn ($c) => [$c => trim((string) ($values[$c] ?? ''))])->all();
    }

    private function links(array $links): array
    {
        return collect($links)->filter(fn ($l) => ! empty($l['url']) && collect($l['label'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty())
            ->map(fn ($l) => ['url' => $l['url'], 'label' => $this->langs($l['label'] ?? [])])->values()->all();
    }
}
