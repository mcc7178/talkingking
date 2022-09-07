<?php

namespace App\Controller;

use App\Model\Circular;
use Hyperf\DbConnection\Db;

class CircularController extends AbstractController
{
    public function list()
    {
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);

        $offset = ($page - 1) * $size;
        $model = Db::table('circular');
        $count = $model->count();
        $list = $model
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($size)
            ->get()
            ->each(function ($item) {
                $item->statusDesc = $item->status == 0 ? '下架' : '发布';
            })
            ->toArray();
        return $this->success([
            'list' => $list,
            'count' => $count
        ]);
    }

    public function info(int $id)
    {
        $info = Circular::query()->findOrFail($id)->toArray();
        $info['statusDesc'] = $info['status'] == 0 ? '下架' : '发布';

        return $this->success([
            'list' => $info
        ]);
    }
}