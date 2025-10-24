<?php
/*
Plugin Name: CrawlxxvnMrLucky
Plugin URI: https://t.me/lorenkidkubi
Description: Plugin tự động crawl và import phim từ xxvnapi hoặc vsphim về WordPress post thường. Hỗ trợ phân loại, tags, gán chuyên mục, lưu link video embed iframe.
Version: 1.7
Author: MrLucky
Author URI: https://github.com/mrlucky94
*/

if (!defined('ABSPATH')) exit;

// --- Enqueue assets
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'toplevel_page_crawlxxvnmrlucky') {
        wp_enqueue_style('crawlxxvnmrlucky-admin', plugin_dir_url(__FILE__) . 'admin.css');
        wp_enqueue_script('crawlxxvnmrlucky-admin', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], '1.7', true);
        wp_localize_script('crawlxxvnmrlucky-admin', 'CrawlxxvnAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('crawlxxvn_nonce')
        ]);
    }
});

// --- Admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'CrawlxxvnMrLucky',
        'CrawlxxvnMrLucky',
        'manage_options',
        'crawlxxvnmrlucky',
        'crawlxxvnmrlucky_admin_page',
        'dashicons-download',
        6
    );
});

// --- Admin page UI
function crawlxxvnmrlucky_admin_page() { ?>
    <div class="wrap crawlxxvn-wrap">
        <div class="crawlxxvn-header">
            <div class="crawlxxvn-logo">
                <svg width="48" height="48" viewBox="0 0 64 64" fill="none"><rect width="64" height="64" rx="12" fill="#353aff"/><path d="M18 32c7-7 21-7 28 0" stroke="#fff" stroke-width="4" stroke-linecap="round"/><circle cx="32" cy="32" r="6" fill="#fff"/><text x="32" y="58" font-size="10" fill="#fff" text-anchor="middle" font-family="Arial">MrLucky</text></svg>
            </div>
            <div class="crawlxxvn-info">
                <h1>CrawlxxvnMrLucky</h1>
                <div class="crawlxxvn-author">
                    <b>Tác giả:</b> MrLucky<br>
                    <b>Plugin WordPress auto crawl phim từ xxvnapi.com hoặc vsphim.com sử dụng cho tất cả các website wordpress.</b><br>
                    <b> Yêu cầu Theme</b> phải thêm trường field tên "_post_play" áp trong Bài viết post. để có ô nhập link nhúng player trong bài.
                </div>
            </div>
        </div>
        <div class="crawlxxvn-guide">
            <h2>Hướng dẫn sử dụng</h2>
            <ol>
                <li><b>Bấm nút "Check API"</b> để kiểm tra kết nối & lấy tổng số video và tổng số trang từng chuyên mục.</li>
                <li><b>Chọn nguồn crawl:</b> Phim mới cập nhật hoặc theo chuyên mục gốc (từ API).</li>
                <li><b>Chọn số trang cần crawl</b> hoặc chọn "Tất cả".</li>
                <li><b>Bấm "Bắt đầu Crawl Collect"</b> để tải phim về website.</li>
                <li>Plugin tự động:
                    <ul>
                        <li>Chống trùng lặp video đã import, Import image API về thư viện site</li>
                        <li>Tạo chuyên mục mới (nếu chưa có) và gán đúng</li>
                        <li>Lưu link player vào trường <b>"_post_play"</b> (theme không có trường nhập link embed iframe sẽ không có player)<br/> Sử dụng Plugin ACF để tạo trường field "_post_play" có ô nhập link nhúng player trong bài.</li>
                    </ul>
                </li>
            </ol>
            <p>— <i><strong><a href="https://t.me/lorenkidkubi" target="_blank" rel="noopener">Bấm để báo cáo lỗi</a> </strong>hoặc <strong><a href="https://mmo4me.com/threads/share-code-server-upload-hls-m3u8-tu-lam-server-rieng-tren-website.496209/post-8363994">tải miễn phí code Server HLS free để lưu trữ video riêng biệt</a></strong></i></p>
        </div>
        <div class="crawlxxvn-actions">
            <button id="crawlxxvn-check-api" class="button button-primary">Check API</button>
            <div id="crawlxxvn-check-result" style="margin-top:16px"></div>
            <div id="crawlxxvn-crawl-form" style="display:none"></div>
            <div id="crawlxxvn-progress"></div>
        </div>
    </div>
<?php }

// --- Helper: request API GET
function crawlxxvn_remote_get($url) {
    $response = wp_remote_get($url, ['timeout' => 30]);
    if (is_wp_error($response)) return false;
    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}

// --- Helper: retry lấy dữ liệu vsphim nhiều lần
function crawlxxvn_remote_get_with_retry($url, $max_retry = 6, $sleep = 2) {
    for ($i=0; $i<$max_retry; $i++) {
        $json = crawlxxvn_remote_get($url);
        if ($json && (
            (isset($json['items']) && is_array($json['items']) && count($json['items'])) ||
            (isset($json['pagination']) && isset($json['pagination']['totalPages']))
        )) {
            return $json;
        }
        sleep($sleep);
    }
    return false;
}

// --- AJAX: Check API chuyên mục vsphim.com + bổ sung chuyên mục theo country
add_action('wp_ajax_crawlxxvn_check_api', function() {
    check_ajax_referer('crawlxxvn_nonce', 'nonce');
    $source_api = $_POST['source_api'] ?? 'xxvnapi';

    if ($source_api=='vsphim') {
        // Danh sách CHUYÊN MỤC (category)
        $vsphim_categories = [
            ['name'=>"Trung Quốc", 'slug'=>"phim-sex-trung-quoc"],
            ['name'=>"JAV", 'slug'=>"jav"],
            ['name'=>"XVideos", 'slug'=>"xvideos"],
            ['name'=>"VLXX", 'slug'=>"vlxx"],
            ['name'=>"XNXX", 'slug'=>"xnxx"],
            ['name'=>"Việt Sub", 'slug'=>"phim-sex-viet-sub"],
            ['name'=>"Không Che", 'slug'=>"phim-sex-khong-che"],
            ['name'=>"Âu Mỹ (category)", 'slug'=>"phim-sex-chau-au"],
            ['name'=>"Thủ Dâm", 'slug'=>"thu-dam"],
            ['name'=>"Vụng Trộm", 'slug'=>"phim-sex-vung-trom"],
            ['name'=>"Loạn luân", 'slug'=>"phim-sex-loan-luan"],
            ['name'=>"3D", 'slug'=>"3d"],
            ['name'=>"Loli", 'slug'=>"loli"
        ];

        // Danh sách QUỐC GIA (country) — theo yêu cầu
        $vsphim_countries = [
            ['name'=>"Việt Nam", 'slug'=>"viet-nam"],   // sẽ thành slug "country:viet-nam" trong dropdown
            ['name'=>"Nhật Bản", 'slug'=>"nhat-ban"],
            ['name'=>"Âu Mỹ", 'slug'=>"chau-au"],
        ];

        $categories = [];
        $total_all  = 0;

        // Lấy số trang/tổng cho CATEGORY
        foreach($vsphim_categories as $cat) {
            $api = 'https://nguon.vsphim.com/api/danh-sach?category='.$cat['slug'].'&page=1';
            $json = crawlxxvn_remote_get_with_retry($api, 5, 2);
            $total_cat = isset($json['pagination']['totalItems']) ? intval($json['pagination']['totalItems']) : 0;
            $last_page = isset($json['pagination']['totalPages']) ? intval($json['pagination']['totalPages']) : 1;
            $categories[] = [
                'name' => $cat['name'],
                'slug' => $cat['slug'], // giữ nguyên slug gốc cho category
                'total' => $total_cat,
                'last_page' => $last_page
            ];
            $total_all += $total_cat;
        }

        // Lấy số trang/tổng cho COUNTRY (slug thêm tiền tố country:)
        foreach($vsphim_countries as $c) {
            $api = 'https://nguon.vsphim.com/api/danh-sach?country='.$c['slug'].'&page=1';
            $json = crawlxxvn_remote_get_with_retry($api, 5, 2);
            $total_ct = isset($json['pagination']['totalItems']) ? intval($json['pagination']['totalItems']) : 0;
            $last_page = isset($json['pagination']['totalPages']) ? intval($json['pagination']['totalPages']) : 1;
            $categories[] = [
                'name' => $c['name'].' (country)',  // ghi chú để phân biệt trong dropdown
                'slug' => 'country:'.$c['slug'],    // QUAN TRỌNG: thêm prefix để JS không cần đổi
                'total'=> $total_ct,
                'last_page' => $last_page
            ];
            $total_all += $total_ct;
        }

        // Lấy tổng phim mới cập nhật
        $api_new  = 'https://nguon.vsphim.com/api/danh-sach/phim-moi-cap-nhat?page=1';
        $json_new = crawlxxvn_remote_get_with_retry($api_new, 5, 2);
        $total_new = isset($json_new['pagination']['totalItems']) ? intval($json_new['pagination']['totalItems']) : 0;

        wp_send_json([
            'success'=>true,
            'msg'=>'Lấy dữ liệu thành công từ vsphim.com',
            'total'=>$total_new, // tổng phim mới cập nhật
            'categories'=>$categories
        ]);
    } else {
        // ---------- XXVNAPI.COM (cũ) ----------
        $api_url = 'https://www.xxvnapi.com/api/phim-moi-cap-nhat';
        $res = wp_remote_get($api_url, ['timeout'=>15]);
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);

        $total = !empty($data['total']) ? $data['total'] : (is_array($data['movies']) ? count($data['movies']) : 0);

        // Danh sách chuyên mục tĩnh
        $xxvn_categories = [
            ['name'=>