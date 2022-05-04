<?php
new raffle_event_custom_post([
    "register_options" => [
        "show_in_menu" => "raffle",
        "taxonomies" => ["category"],
        "supports" => [
            "title",
            "editor",
            "thumbnail"
        ]
    ],
    "post_type_name" => "raffle_nft_group",
    "post_label_name" => "NFT 그룹"
], [
    "passed_variables" => function ($post) {
        return ["test" => "test"];
    },
    "custom_box_html" => function ($post) {
        extract((array)$post);
        extract(array_map(function ($v) {
            return $v[0];
        }, get_post_meta($ID)));
        ob_start();
        $nft_list = @unserialize($nft_list) ?: [];
        $duplication = @$duplication ?: "0";
?>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">이미지 업로드</th>
                <td>
                    <div class="img_container" style="margin-bottom:10px;">
                        <div class="char-img img-thumb" style="
                            width:100px;
                            height:100px;
                            background-size:contain;
                            background-position:center;
                            background-repeat:no-repeat;
                            background-color:#efefef;
                            border:1px solid #000;
                            background-image:url('<?= wp_get_attachment_url(@$char_img) ?>');
                        "></div>
                        <?= @$char_img ? '<input type="hidden" name="char_img" value="' . $char_img . '"/>' : "" ?>
                        <a data-type="char" data-label="캐릭터 이미지를 업로드 합니다." class="media-button button button-small button-primary">캐릭터 이미지 업로드</a>
                    </div>
                    <div class="img_container">
                        <div class="bg-img img-thumb" style="
                            width:300px;
                            height:100px;
                            background-size:contain;
                            background-position:center;
                            background-repeat:no-repeat;
                            background-color:#efefef;
                            border:1px solid #000;
                            background-image:url('<?= wp_get_attachment_url(@$bg_img) ?>');
                        "></div>
                        <?= @$bg_img ? '<input type="hidden" name="bg_img" value="' . $bg_img . '"/>' : "" ?>
                        <a data-type="bg" data-label="배경 이미지를 업로드 합니다." class="media-button button button-small button-secondary">배경 이미지 업로드</a>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">분양 nft 리스트</th>
                <td>
                    <input type="text" id="nft_list_input" list="nft_list">
                    <button type="button" id="nft_list_input_button" class="button button-primary">등록하기</button>
                    <script>
                        const nft_lists = [<?= implode(",", array_map(function ($x) {
                                                return $x = "'" . $x . "'";
                                            }, $nft_list ?: [])) ?>];
                    </script>
                    <datalist id="nft_list">
                        <?php
                        (function () {
                            $nft_list = "";
                            global $wpdb;
                            $data_list = $wpdb->get_results("SELECT DISTINCT 
                                meta.meta_value, thumb.ID, thumb.post_title AS 'title'
                                FROM
                                (SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE meta_value = 'nft_type') AS meta,
                                (SELECT ID, post_type, post_title FROM $wpdb->posts WHERE post_type = 'attachment') AS thumb
                                WHERE 
                                meta.post_id = thumb.ID
                                ", ARRAY_A);
                            foreach ($data_list as $value) {
                                $value['title'] = $value['title'] === "budong" ? "부동" : $value['title'];
                                $nft_list .= "<option value=\"$value[title] (media:$value[ID])\"/>";
                            }
                            echo $nft_list;
                        })();
                        (function () {
                            $nft_list = "";
                            foreach (get_posts([
                                "nopaging" => true,
                                "post_type" => "nft_data"
                            ]) as $value) {
                                $nft_list .= "<option value=\"$value->post_title ($value->ID)\"/>";
                            }
                            echo $nft_list;
                        })();
                        ?>
                    </datalist>
                    <hr>
                    <div id="added_nft_list">

                    </div>
                </td>
            </tr>
        </tbody>
    </table>
<?php
        $html = ob_get_clean();
        echo $html;
    },
    "save_postdata" => function ($post_id) {
        date_default_timezone_set("Asia/Seoul");
        $list = ["start_date", "start_time", "due_date", "due_time", "end_date", "end_time", "full_count", "due_type", "nft_list", "condition", "bg_img", "char_img", "duplication"];
        foreach ($list as $value) {
            if (key_exists($value, $_POST)) {
                update_post_meta($post_id, $value, $_POST[$value]);
            }
        }
        if (key_exists('start_date', $_POST) && key_exists('start_time', $_POST)) {
            update_post_meta($post_id, "start_time_int", strtotime($_POST['start_date'] . " " . $_POST['start_time']) + (HOUR_IN_SECONDS * 9));
        }
        if (key_exists('end_date', $_POST) && key_exists('end_time', $_POST)) {
            update_post_meta($post_id, "end_time_int", strtotime($_POST['end_date'] . " " . $_POST['end_time']) + (HOUR_IN_SECONDS * 9));
        }
    }
], [],  [
    "raffle_event_custom_post_metadata" => [function ($arr, $post) {
        if ($post["type"] === "raffle_event_post") {
            $event_id = $post["id"];
            $new_arr = [];
            foreach ($arr as $key => $value) {
                if ($v = @unserialize($value)) {
                    $x = $v;
                } else {
                    $x = $value;
                }
                $new_arr[$key] = $x;
            }
            $new_arr["participants"] = 0;
            $new_arr["nft_list_ids"] = [];
            $new_arr["participants_list"]  = [];
            if (key_exists("nft_list", $new_arr)) {
                foreach ($new_arr["nft_list"] as $key => $value) {
                    preg_match("/-.*?:?(\d+)/", $value, $match);
                    $id = $match[1];
                    $new_arr["participants"] += count(get_post_meta($id, "participants_list")) ?: 0;
                    $new_arr["participants_list"] = array_merge($new_arr["participants_list"], get_post_meta($id, "participants_list"));
                    array_push($new_arr["nft_list_ids"], $id);
                }
            }
            $event_instance = new RaffleEvent_EventData($event_id);
            $event_instance->update_event_status();
            return $new_arr;
        }
        return $arr;
    }, 10, 2]
]);