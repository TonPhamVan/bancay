<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Seo;

final class LandscapeController extends Controller
{
    public function __invoke(Seo $seo)
    {
        $ideas = [
            ['title' => 'Gợi ý bố trí ban công', 'image' => '/storefront/landscape-ideas/balcony-d47c632faafd.webp',
                'alt' => 'Ảnh ý tưởng ban công với cây treo, chậu cây và tiểu cảnh nước', 'width' => 960, 'height' => 1286],
            ['title' => 'Tiểu cảnh nước và cây xanh', 'image' => '/storefront/landscape-ideas/water-feature-f778c80b9852.webp',
                'alt' => 'Ảnh ý tưởng tiểu cảnh chậu men xanh phối cây và thác nước nhỏ', 'width' => 960, 'height' => 1432],
            ['title' => 'Phối cây trong không gian hẹp', 'image' => '/storefront/landscape-ideas/plant-composition-52697b6cc82e.webp',
                'alt' => 'Ảnh ý tưởng phối lưỡi hổ, cây lá màu và cây xanh dọc lan can', 'width' => 960, 'height' => 1432],
        ];

        return view('home.landscape', ['seo' => $seo->page('Tư vấn thiết kế cây xanh & cảnh quan', '/tu-van-canh-quan',
            'Trao đổi nhu cầu cây xanh văn phòng, ban công, tiểu cảnh và hệ thống tưới tại Hà Nội. Liên hệ cửa hàng để được tư vấn theo không gian thực tế.',
            image: $ideas[0]['image']), 'ideas' => $ideas]);
    }
}
