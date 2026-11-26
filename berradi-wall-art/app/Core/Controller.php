<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller with view/redirect/validation helpers.
 */
abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /** @param array<string,mixed> $data */
    protected function view(string $view, array $data = [], int $status = 200): Response
    {
        return Response::html(View::make($view, $data), $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    /** @param array<string,string|int> $params */
    protected function redirectRoute(string $name, array $params = []): Response
    {
        return Response::redirect(route($name, $params));
    }

    protected function back(): Response
    {
        $ref = (string) $this->request->header('Referer', '');
        $root = $this->request->rootUrl();
        // same origin only: "https://shop.ma.evil.example" must not pass as "https://shop.ma"
        if ($ref !== '' && ($ref === $root || str_starts_with($ref, rtrim($root, '/') . '/'))) {
            return Response::redirect($ref);
        }
        return Response::redirect(url('/'));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Validate request input. On failure flashes errors + old input and throws a redirect back.
     *
     * @param array<string,string|list<string>> $rules
     * @param array<string,string> $labels
     * @return array<string,mixed>
     */
    protected function validate(array $rules, array $labels = []): array
    {
        $v = Validator::make($this->request->all(), $rules, $labels);
        if ($v->fails()) {
            throw new ValidationException($v->firstErrors(), $this->request->all());
        }
        return $v->validated();
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash('alert', ['type' => $type, 'message' => $message]);
    }
}
