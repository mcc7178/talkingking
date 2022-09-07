<?php

namespace App\Controller;

use App\Model\Banner;
use App\Model\Circular;
use App\Model\Partner;
use App\Model\Product;

class HomeController extends AbstractController
{
    public function list()
    {
        $banner = Banner::query()->where('status', 1)->get()->toArray();
        $circular = Circular::query()->where('status', 1)->get()->toArray();
        $product = Product::query()->where('is_show', 1)->limit(2)->get()->toArray();
        $partner = Partner::query()->where('status', 1)->get()->toArray();

        return $this->success([
            'banner' => $banner,
            'circular' => $circular,
            'product' => $product,
            'partner' => $partner
        ]);
    }
}