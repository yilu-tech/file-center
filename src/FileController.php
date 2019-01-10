<?php

namespace YiluTech\FileCenter;

use Illuminate\Http\Request;

class FileController
{
    public function info(Request $request)
    {
        app('validator')->make($request->input(), [
            'prefix' => 'nullable|string|max:64'
        ])->validate();

        $server = new Server($request->input('bucket'));

        return [
            'data' => [
                'root' => $server->getRoot(),
                'host' => $server->getHost()
            ],
            'errcode' => 0
        ];
    }

    public function move(Request $request)
    {
        app('validator')->make($request->input(), [
            'paths' => 'required|array|min:1',
            'bucket' => 'required|string|max:16',
            'prefix' => 'nullable|string|max:64'
        ])->validate();
        $server = new Server($request->input('bucket'), $request->input('prefix'));
        try {
            foreach ($request->input('paths') as $path) {
                $argv = is_array($path) ? [$path['from'], $path['to']] : [$path];
                if (!$server->move(...$argv))
                    throw new \Exception();
            }

            return ['errcode' => 0, 'data' => 1];
        } catch (\Exception $exception) {
            $server->rollBack();
            return ['errmsg' => $exception->getMessage(), 'errcode' => -1];
        }
    }

    public function delete(Request $request)
    {
        app('validator')->make($request->input(), [
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
            'bucket' => 'required|string|max:16',
            'prefix' => 'nullable|string|max:64'
        ])->validate();

        $server = new Server($request->input('bucket'), $request->input('prefix'));

        try {
            if ($server->delete($request->input('paths'))) {
                return ['errcode' => 0, 'data' => 1];
            }
            return ['errmsg' => 'fail', 'errcode' => -1];
        } catch (\Exception $exception) {
            return ['errmsg' => $exception->getMessage(), 'errcode' => -1];
        }
    }

    public function recover(Request $request)
    {
        app('validator')->make($request->input(), [
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
            'bucket' => 'required|string|max:16',
        ])->validate();

        $server = new Server($request->input('bucket'));

        try {
            foreach ($request->input('paths') as $path)
                if (!$server->recovery($path))
                    throw new \Exception();
            return ['errcode' => 0, 'data' => 1];
        } catch (\Exception $exception) {
            $server->rollBack();
            return ['errmsg' => $exception->getMessage(), 'errcode' => -1];
        }
    }
}
