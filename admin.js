jQuery(document).ready(function($){

    let categories_data = [];
    let crawl_paused = false, crawl_stopped = false, current_idx = 0, movies_list = [];

    if($('#crawlxxvn-source-api').length===0){
        $('.crawlxxvn-actions').prepend(`
            <label style="margin-right:16px;"><b>Nguồn API:</b>
                <select id="crawlxxvn-source-api">
                    <option value="xxvnapi">xxvnapi.com</option>
                    <option value="vsphim">vsphim.com</option>
                </select>
            </label>
        `);
    }

    $(document).on('change', '#crawlxxvn-source-api', function(){
        $('#crawlxxvn-check-result, #crawlxxvn-crawl-form, #crawlxxvn-progress').html('');
        $('#crawlxxvn-crawl-form').hide();
    });

    function getSourceAPI() {
        return $('#crawlxxvn-source-api').val() || 'xxvnapi';
    }

    $('#crawlxxvn-crawl-form').hide();
    $('#crawlxxvn-progress').html('');

    $('#crawlxxvn-check-api').on('click', function(){
        var btn = $(this);
        btn.prop('disabled', true).text('Đang kiểm tra...');
        $('#crawlxxvn-check-result, #crawlxxvn-crawl-form, #crawlxxvn-progress').html('');
        $.post(CrawlxxvnAjax.ajaxurl, {
            action:'crawlxxvn_check_api', 
            nonce:CrawlxxvnAjax.nonce,
            source_api: getSourceAPI()
        }, function(res){
            btn.prop('disabled', false).text('Check API');
            if (!res.success) {
                $('#crawlxxvn-check-result').html('<div class="notice notice-error">'+res.msg+'</div>');
                return;
            }
            let total = res.total || 'Không xác định';
            categories_data = res.categories || [];
            let catTable = '<table style="width:100%;margin:12px 0"><thead><tr>' +
                '<th style="text-align:left">Chuyên mục</th>' +
                '<th>Tổng video</th>' +
                '<th>Tổng trang</th>' +
                '</tr></thead><tbody>';
            categories_data.forEach(function(cat){
                catTable += '<tr><td>'+cat.name+'</td><td>'+cat.total+'</td><td>'+cat.last_page+'</td></tr>';
            });
            catTable += '</tbody></table>';
            $('#crawlxxvn-check-result').html(
                '<div class="notice notice-success">'+res.msg+'<br><b>Tổng số video mới cập nhật:</b> '+total+'</div>' +
                '<div><b>Tổng video và trang theo từng chuyên mục:</b>'+catTable+'</div>'
            );
            buildCrawlForm();
        });
    });

    function buildCrawlForm() {
        let cat_options = '<option value="">-- Chọn chuyên mục --</option>';
        categories_data.forEach(function(cat){ 
            cat_options += '<option value="'+cat.slug+'">'+cat.name+' ('+cat.total+' video, '+cat.last_page+' trang)</option>'; 
        });

        let html = '<div><label><input type="radio" name="source" value="new" checked> Phim mới cập nhật</label> ';
        html += '<label style="margin-left:16px"><input type="radio" name="source" value="category"> Crawl theo chuyên mục:</label> ';
        html += '<select id="crawlxxvn-category" name="category" style="min-width:180px" disabled>'+cat_options+'</select></div>';
        html += '<div style="margin:16px 0 8px">Từ page <input type="number" id="crawlxxvn-from-page" min="1" value="1" style="width:50px"> đến <input type="number" id="crawlxxvn-to-page" min="1" value="1" style="width:50px"> hoặc <label><input type="checkbox" id="crawlxxvn-allpage"> Toàn bộ</label></div>';
        html += '<button id="crawlxxvn-crawl-btn" class="button button-secondary">Bắt đầu Crawl Collect</button>';
        $('#crawlxxvn-crawl-form').html(html).show();

        $('input[name=source]').change(function(){
            let isCat = $('input[name=source]:checked').val()==='category';
            $('#crawlxxvn-category').prop('disabled', !isCat);
        });

        $('#crawlxxvn-allpage').change(function(){
            let on = $(this).is(':checked');
            $('#crawlxxvn-to-page').prop('disabled', on);
        });

        $('#crawlxxvn-crawl-btn').off().on('click', function(){
            crawl_paused = false; crawl_stopped = false; current_idx = 0; movies_list = [];
            let source = $('input[name=source]:checked').val();
            let slug = source==='category' ? $('#crawlxxvn-category').val() : '';
            if (source==='category' && !slug) {
                alert('Bạn phải chọn chuyên mục!');
                return;
            }
            let from_page = parseInt($('#crawlxxvn-from-page').val());
            let to_page = $('#crawlxxvn-allpage').is(':checked') ? getLastPage(slug) : parseInt($('#crawlxxvn-to-page').val());
            if (isNaN(from_page) || from_page<1) from_page=1;
            if (isNaN(to_page) || to_page<from_page) to_page=from_page;
            if (to_page-from_page>=20) { alert('Mỗi lần crawl tối đa 20 page để tránh quá tải!'); return;}
            $('#crawlxxvn-progress').html('Đang lấy danh sách video...');
            // Không disable crawl-btn khi lấy movie để user thử lại ngay nếu cần
            let data = {
                action:'crawlxxvn_get_movies',
                nonce:CrawlxxvnAjax.nonce,
                source:source,
                slug:slug,
                from_page:from_page,
                to_page:to_page,
                source_api: getSourceAPI()
            };
            getMoviesWithRetry(data, 6, 1200, function(res){
                if (!res.success || !res.movies.length) {
                    $('#crawlxxvn-progress').html('Không lấy được video sau nhiều lần thử lại. Hãy thử lại sau hoặc kiểm tra API.');
                    // Không disable lại nút để bấm thử lại nhanh!
                    return;
                }
                movies_list = res.movies;
                $('#crawlxxvn-progress').html('<button id="crawlxxvn-pause-btn" class="button">Tạm dừng</button> <span id="crawlxxvn-status"></span><div id="crawlxxvn-import-log"></div>');
                $('#crawlxxvn-pause-btn').on('click', function(){
                    crawl_paused = true; $(this).text('Đang tạm dừng...');
                });
                importOne(0);
            });
        });
    }

    function getMoviesWithRetry(data, retries, delay, cb) {
        $.post(CrawlxxvnAjax.ajaxurl, data, function(res){
            if (!res.success || !res.movies.length) {
                if (retries > 0) {
                    $('#crawlxxvn-progress').append('<div style="color:#FF6600;">Thử lại lấy video... ('+(7-retries)+'/6), chờ '+delay/1000+'s...</div>');
                    setTimeout(function(){
                        getMoviesWithRetry(data, retries-1, delay+1000, cb);
                    }, delay);
                } else {
                    cb(res);
                }
                return;
            }
            cb(res);
        }).fail(function(jqXHR, textStatus) {
            if (retries > 0) {
                $('#crawlxxvn-progress').append('<div style="color:#FF3300;">Lỗi mạng/API, thử lại... ('+(7-retries)+'/6)</div>');
                setTimeout(function(){
                    getMoviesWithRetry(data, retries-1, delay+1000, cb);
                }, delay);
            } else {
                cb({success:false, movies:[]});
            }
        });
    }

    function getLastPage(slug){
        let found = categories_data.find(cat => cat.slug === slug);
        if (found && found.last_page) return found.last_page;
        return 10;
    }

    function importOne(idx){
    if (crawl_paused || crawl_stopped || idx>=movies_list.length) {
        $('#crawlxxvn-status').html('Đã dừng hoặc hoàn tất.');
        $('#crawlxxvn-crawl-btn').prop('disabled', false);
        return;
    }
    let m = movies_list[idx];
    $('#crawlxxvn-status').html('<b>Đang import:</b> '+m.name+' ('+(idx+1)+'/'+movies_list.length+')');

    // Lấy slug chuyên mục hiện tại để truyền vào từng phim
    let source = $('input[name=source]:checked').val();
    let slug = source==='category' ? $('#crawlxxvn-category').val() : '';

    $.post(CrawlxxvnAjax.ajaxurl, {
        action:'crawlxxvn_import_one', nonce:CrawlxxvnAjax.nonce,
        movie: JSON.stringify(m),
        source_api: getSourceAPI(),
        category_slug: slug   // THÊM DÒNG NÀY!
    }, function(result){
        let color = 'green';
        if (result.duplicated) color='gray';
        else if (result.error) color='red';
        $('#crawlxxvn-import-log').append(
            '<div style="color:'+color+';">'+(idx+1)+'. '+m.name+' - '+(result.duplicated?'Đã tồn tại':result.error?'Lỗi':'OK')+'</div>'
        );
        setTimeout(function(){ importOne(idx+1); }, 500);
    });
}
});
