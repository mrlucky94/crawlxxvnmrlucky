# CrawlxxvnMrLucky – Plugin Tự Động Crawl Phim cho WordPress
[![GitHub release downloads](https://img.shields.io/github/downloads/mrlucky94/crawlxxvnmrlucky/latest/total?label=Used)](https://github.com/mrlucky94/crawlxxvnmrlucky/releases/latest)
<br/> 
**Tác giả:** [MrLucky](https://github.com/mrlucky94)  
**Phiên bản:** 1.7  
**License:** GNU GPL v2.0

## Chức năng

- **Tự động crawl và import** phim từ xxvnapi hoặc vsphim về post thường của WordPress
- **Hỗ trợ phân loại, tags, chuyên mục** (category), gán đúng chuyên mục/country, lưu link video player nhúng iframe
- **Chống trùng lặp video**, import poster về thư viện site
- **Tương thích mọi theme**, yêu cầu theme có trường custom field `_post_play` để lưu link player (nên dùng plugin ACF để tạo field này)

## Cài đặt

1. Tải plugin về và giải nén vào thư mục `wp-content/plugins/crawlxxvnmrlucky`
2. Kích hoạt plugin trong Dashboard > Plugins

## Sử dụng

1. Vào menu **CrawlxxvnMrLucky** trên Admin Dashboard
2. Nhấn **Check API** để kiểm tra kết nối và lấy danh sách phim, chuyên mục
3. Chọn nguồn crawl (phim mới hoặc chuyên mục/country), chọn số page cần crawl hoặc toàn bộ
4. Bấm **Bắt đầu Crawl Collect** để import phim về website. Plugin tự động chống trùng, gán chuyên mục, import ảnh, lưu link player

## Yêu cầu

- Theme WordPress phải có trường custom field `_post_play` trong post (dùng ACF để tạo nếu chưa có)
- PHP >= 7.0, WordPress >= 5.0

## Liên hệ & Hỗ trợ

- Báo lỗi, góp ý: [Telegram](https://t.me/lorenkidkubi)
- Tải code server HLS miễn phí: [MMO4ME](https://mmo4me.com/threads/share-code-server-upload-hls-m3u8-tu-lam-server-rieng-tren-website.496209/post-8363994)


**Cảm ơn bạn đã sử dụng plugin!**
