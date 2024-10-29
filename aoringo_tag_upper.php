<?php
/*
  Plugin Name: aoringo TAG maker
  Description: 記事中の単語を拾い上げてタグと同じものがあればタグ化します。
  Version: 0.1.6
  Author: aoringo
  Author URI: http://cre.jp/honyomusi/
 */

function post_tag_maker($test) {
  if ($_POST[content] == "") {
    return;
  }
  global $post;
  //記事内容読み込み
  $okikae = array(
      "\r\n" => "\n",
      "\r" => "\n",
      '</dd>' => "\n",
      '</p>' => "\n",
      '<br>' => "\n",
      '<br />' => "\n",
      '</dl>' => "\n",
      '</dt>' => "\n",
  );
  $postcontent = strtr($_POST[content], $okikae);
  $postcontent .="\n" . $_POST[post_title];
  if (get_option('tagtagjidou') != "") {
    $jidoutagsettei = str_replace(".", "\.", get_option('tagtagjidou'));
    $jidoutagsettei = str_replace(",", "|", $jidoutagsettei);
    preg_match_all("/(?<=$jidoutagsettei).+?(?=[ \t\n\r\f\v　<])/iu", $postcontent, $out);
    $countcate = count($out[0]);
    for ($i = 0; $i < $countcate; $i++) {
      if (mb_detect_encoding($out[0][$i]) == "UTF-8") {
        $jidoutaglist .= $out[0][$i] . ",";
      }
    }
  }
  if (get_option('adrestag') == 1) {
//    /.*(?<=keywords=)([a-zA-Z%0-9 ]+)/ium
//    /(?<=\/)(%.+?)(?=\/dp)/ium
    preg_match_all("/h?ttps?.+(?=[ \t\n\r\f\v><])/ium", $postcontent, $adres);
    foreach ($adres[0] as $vary) {
      $utlvaru = NULL;
      if (strpos($vary, "keywords=") !== false) {
        $utlvaru = preg_replace("/.*(?<=keywords=)([a-zA-Z%0-9 ]+)/iu", "$1", $vary);
      } elseif (strpos($vary, "/dp/") !== false) {
        $utlvaru = preg_replace("/.*(?<=\/)(%.+?)(?=\/dp).*/iu", "$1", $vary);
      } else {
        continue;
      }
      $utlvaru = urldecode($utlvaru);
      if (mb_detect_encoding($utlvaru) == "UTF-8") {
        $urltag .= $utlvaru . ",";
      }
    }
//    $urltag = mb_convert_encoding($urltag, 'utf8', 'sjis');
  }
  $postcontent = strip_tags(stripslashes($postcontent));
  //タグ一覧を取得。一つずつ内容と比較して同一の物があればタグリストに追加する。
  $rendotaglist = explode(",", get_option('tagtag_rendo'));
  $rendotaglistcount = count($rendotaglist);
  $ngtaglist = explode(",", get_option('tagtag_ng'));
  $allngflag = get_option('tagtagallng');
  foreach (get_tags('hide_empty=0') as $tag) {
    $flag = 0;
    if (strpos($postcontent, $tag->name) !== false) {
//      連動タグ動作
      for ($i = 0; $i < $rendotaglistcount; $i++) {
        if ($rendotaglist[$i] == $tag->name)
          $tagtag .= $rendotaglist[$i + 1] . ",";
        ++$i;
      }
//      NGタグ検知
      if ($allngflag != 1) {
        foreach ($ngtaglist as $ngtag) {
          if ($ngtag == $tag->name)
            $flag = 1;
        }
      }else {
        $flag = 1;
      }
//      フラグが立たなければタグに登録できる。
      if ($flag != 1) {
        $tagtag .= $tag->name . ",";
      }
    }
  }
  //現在のタグと追加するタグを合わせる。同一名称はwordpress側で排除されるので気にしない☆$jidoutaglist
  $taglist = $tagtag . $jidoutaglist . $urltag;
  $taglist = rtrim(preg_replace("/,(?=,)/iu", "", $taglist), ",");
//  file_put_contents("sample3.txt", $taglist);
  wp_add_post_tags($_POST[post_ID], $taglist);
}

add_action('save_post', 'post_tag_maker', 11);

// ダッシュボード設定へのリンクを追加
function aoringotagtag_option_menu() {
  add_submenu_page('options-general.php', 'aoringo TAG upperの設定', 'aoringo TAG upperの設定', 8, __FILE__, 'aoringotagtag_admin_page');
}

add_action('admin_menu', 'aoringotagtag_option_menu');

//***************************************************************** 以下設定画面用コード ****************************************************//
// 設定画面構成コード
function aoringotagtag_admin_page() {
  //設定保存用処理、改行や今後の処理に関わりそうな文字を整理する。タグなども除去している。
  $jokyo = array("\n" => "", "\r" => "", "$" => "", '"' => "&quot;",
      "'" => "&apos;",
      '\\' => "",
      "" => "&nbsp;",
      "<" => "&lt;",
      ">" => "&gt;",
      "@" => "&copy;",
      "$" => "＄",);

  if ($_POST['posted'] == 'Y') {
    update_option('tagtag_ng', rtrim(preg_replace("/,(?=,)/iu", "", strtr(strip_tags(stripslashes($_POST['tagtag_ng'])), $jokyo)), ","));
    update_option('tagtag_rendo', rtrim(preg_replace("/,(?=,)/iu", "", strtr(strip_tags(stripslashes($_POST['tagtag_rendo'])), $jokyo)), ","));
    update_option('tagtagallng', $_POST['tagtagallng']);
    update_option('adrestag', $_POST['adrestag']);

//    五個以上の設定は消去
    $jidoutaghairetu = explode(",", rtrim(preg_replace("/,(?=,)/iu", "", strtr(strip_tags(stripslashes($_POST['tagtagjidou'])), $jokyo)), ","));
    $jidoutaghairetucount = count($jidoutaghairetu);
    for ($i = 0; $i < $jidoutaghairetucount; $i++) {
      if ($i < 5)
        $jidoutag .= $jidoutaghairetu[$i] . ",";
    }
    update_option('tagtagjidou', rtrim($jidoutag, ","));
    //if( is_numeric( $_POST[ 'loglog_table_pa_sen'  ] ) >= 100 ) {update_option('loglog_table_pa_sen', strip_tags(stripslashes($_POST['loglog_table_pa_sen'])));}
  }
// htmlで記述するため一旦phpから外れてend文では隠すようにしている。

  if ($_POST['posted'] == 'Y') :
    ?><div class="updated"><p><strong>設定を保存した気がします！</strong></p></div><?php endif; ?>

  <?php if ($_POST['posted'] == 'Y') : ?>
                                                                                                                                                                                                                                                    <!-- order = <?php echo $_POST['order']; ?>, striped = <?php echo stripslashes($_POST['order']); ?>, saved = <?php get_option('fjscp_order'); ?> -->
  <?php endif; ?>
  <!-- おそらく設定画面用のクラスなのだろうこれは -->
  <style type="text/css">
    <!--
    .taglist{
      margin-right: 5px;
      margin-bottom: 5px;
      padding:2px 5px;
      border:solid 1px #5f9ea0;
      border-radius: 10px;
      float:left;
      font-size: 16px;
    }
    .one{
      background-color: #afeeee;
    }
    .two{
      background-color: #e0ffff;
    }
    .twree{
      background-color: #ffc0cb;
      border-color: #b22222;
    }
    .form-table{
      width: 90%;
    }
    .kanma{
      float:left;
      padding:2px 0px;
      margin-bottom: 5px;
      font-size: 16px;
    }
    -->
  </style>
  <div class="wrap">
    <h2>Aoringo TAG upperの設定</h2>
    <form method="post" action="<?php
  echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']);
  // フォームタグはmethodがpostの場合は本文としてデータを送信する。actionにアドレスを入れるとそのアドレスのフォームがリロードされたときなどに入力された状態で出力される。
  ?>">
      <input type="hidden" name="posted" value="Y">
      <p>simple tagsと共存可能。多分。</p>
      <p>要望、報告などは<A Href="http://cre.jp/honyomusi/" Target="_blank">http://cre.jp/honyomusi/</A>までお気軽にどうぞ</p>
      <p class="submit"><input type="submit" name="Submit" class="button-primary" value="変更を保存" /></p>
      <table class="form-table">
        <tr valign="top">
          <th scope="row">このブログに登録されているタグリスト</th>
          <td>
            <?php
            foreach (get_tags('hide_empty=0') as $tag) {
              echo '<span class="taglist one">';
              echo $tag->name;
              echo '</span><span class="kanma">,</span>';
            }
            ?>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="tagtag_ng">NGタグを設定する</label></th>
          <td><textarea name="tagtag_ng" id="tagtag_ng" class="regular-text code" style="width:650px;" rows="2"><?php echo get_option('tagtag_ng'); ?></textarea><br />
            自動でタグ付けしたくない単語を登録してください。カンマ（,）で区切ってください。
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><br>
          <td><font size="+1"><label for="tagtagallng">ＡＬＬ　ＮＧ　　</label></font><input type="checkbox" name="tagtagallng" id="tagtagallng" value="1" <?php if (get_option('tagtagallng')) echo 'checked="checked"'; ?> /><br /><br />
            全てＮＧにします。ＮＧＮＧＮＧＮＧＮＧＮＧＮＧＮ！！！<br />
            ↓処理後は下の単語リストが記事から拾い上げられタグ化します↓
          </td>
        </tr>
        <th scope="row">自動タグ付けする単語リスト</th>
        <td>
          <?php
          $ngtaglist = explode(",", get_option('tagtag_ng'));
          foreach (get_tags('hide_empty=0') as $tag) {
            if (get_option('tagtagallng'))
              break;
            $flag = 0;
            foreach ($ngtaglist as $ngtag) {
              if ($ngtag == $tag->name)
                $flag = 1;
            }
            if ($flag != 1) {
              echo '<span class="taglist two">';
              echo $tag->name;
              echo '</span><span class="kanma">,</span>';
            }
          }
          ?>
        </td>
        <tr valign="top">
          <th scope="row"><label for="tagtag_rendo">連動タグ付け</label><br />
            まどマギ→「まどか☆マギカ」「まどマギ」タグ化</th>
          <td><textarea name="tagtag_rendo" id="tagtag_rendo" class="regular-text code" style="width:650px;" rows="2"><?php echo get_option('tagtag_rendo'); ?></textarea><br />
            連動したいタグ名の後にキーワードを入力してください。カンマ（,）で区切ってください。<br />
            <font color = "red">※1</font>NGタグの前に動作するので、例文の「まどマギ」をタグ化せずに「まどか☆マギカ」だけタグにしたい場合は、ＮＧタグに「まどマギ」を設定して連動設定すれば<br>
            <font color = "red">「まどマギ」を検知→「まどか☆マギカ」を設定＆「まどマギ」はスルー</font><br>
            ということもできます。ティロ☆フィナーレ！<br>
            <font color = "red">※2</font>依存のタグを拾って動作します。<br>
            <?php
            $taglist = explode(",", get_option('tagtag_rendo'));
            $taglist_count = count($taglist);
            for ($i = 0; $i < $taglist_count; $i++) {
              echo '<span class="taglist twree">' . "$taglist[$i] → " . $taglist[++$i] . "</span>";
            }
            ?>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="tagtagjidou">特定単語トリガー自動タグ</label></th>
          <td><textarea name="tagtagjidou" id="tagtagjidou" class="regular-text code" style="width:650px;" rows="2"><?php echo get_option('tagtagjidou'); ?></textarea><br />
            特定の単語からスペース、改行、htmlタグまでを自動でタグ付けします。カンマ（,）で区切ってください。<br />
            例→作品：オオスズメバチＶＳミツバチ　「オオスズメバチＶＳミツバチ」タグ化<br />
            <font color = "red">あまり信用しないこと。安全動作のためにNGワード多数あり。処理の関係上5つまでです。</font><br>
            <?php
            $taglist = explode(",", get_option('tagtagjidou'));
            foreach ($taglist as $tag) {
              echo '<span class="taglist for">';
              echo $tag;
              echo "</span>";
            }
            ?>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">アドレス
          <td><font size="+1"><label for="adrestag">アドレス認識タグ化　　</label></font><input type="checkbox" name="adrestag" id="tagtagallng" value="1" <?php if (get_option('adrestag')) echo 'checked="checked"'; ?> /><br /><br />
            アマゾンなどの日本語キーワードを拾ってタグ化します。連動タグ付けはここでは動作しません。
          </td>
        </tr>
      </table>
      <p class="submit"><input type="submit" name="Submit" class="button-primary" value="変更を保存" /></p>
    </form>
  </div>
  <?php
}

function aoringotagupper_init_option() {
  //インストール時の初期設定
  if (!get_option('aoringotag_installed')) {
    update_option('tagtag_ng', 'aoringo_chan, あおりんごちゃん');
    update_option('tagtag_rendo', 'tagtesttagtest,タグテストタグテスト,teteetetetetetetst,テテテテスト');
    update_option('tagtagjidou', '自動タグテスト：,自動タグタグタグ：');
    update_option('aoringotag_installed', 1);
  }
}

register_activation_hook(__FILE__, 'aoringotagupper_init_option')
?>