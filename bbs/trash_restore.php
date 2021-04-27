<?php
include_once('./_common.php');

$delete_token = get_session('ss_delete_token');
set_session('ss_delete_token', '');

if (!($token && $delete_token == $token))
    alert('토큰 에러로 복구 불가합니다.');

//$wr = sql_fetch(" select * from $write_table where wr_id = '$wr_id' ");

$count_write = $count_comment = 0;

@include_once($board_skin_path.'/delete.head.skin.php');

if ($is_admin == 'super') // 최고관리자 통과
    ;
else if ($is_admin == 'group') { // 그룹관리자
    $mb = get_member($write['mb_id']);
    if ($member['mb_id'] != $group['gr_admin']) // 자신이 관리하는 그룹인가?
        alert('자신이 관리하는 그룹의 게시판이 아니므로 삭제할 수 없습니다.');
    else if ($member['mb_level'] < $mb['mb_level']) // 자신의 레벨이 크거나 같다면 통과
        alert('자신의 권한보다 높은 권한의 회원이 작성한 글은 삭제할 수 없습니다.');
} else if ($is_admin == 'board') { // 게시판관리자이면
    $mb = get_member($write['mb_id']);
    if ($member['mb_id'] != $board['bo_admin']) // 자신이 관리하는 게시판인가?
        alert('자신이 관리하는 게시판이 아니므로 삭제할 수 없습니다.');
    else if ($member['mb_level'] < $mb['mb_level']) // 자신의 레벨이 크거나 같다면 통과
        alert('자신의 권한보다 높은 권한의 회원이 작성한 글은 삭제할 수 없습니다.');
} else if ($member['mb_id']) {
    if ($member['mb_id'] !== $write['mb_id'])
        alert('자신의 글이 아니므로 삭제할 수 없습니다.');
} else {
    if ($write['mb_id'])
        alert('로그인 후 삭제하세요.', G5_BBS_URL.'/login.php?url='.urlencode(get_pretty_url($bo_table, $wr_id)));
    else if (!check_password($wr_password, $write['wr_password']))
        alert('비밀번호가 틀리므로 삭제할 수 없습니다.');
}

$len = strlen($write['wr_reply']);
if ($len < 0) $len = 0;
$reply = substr($write['wr_reply'], 0, $len);

// 원글만 구한다.
$sql = " select count(*) as cnt from $write_table
            where wr_reply like '$reply%'
            and wr_id <> '{$write['wr_id']}'
            and wr_num = '{$write['wr_num']}'
            and wr_is_comment = 0 ";
$row = sql_fetch($sql);
if ($row['cnt'] && !$is_admin)
    alert('이 글과 관련된 답변글이 존재하므로 삭제 할 수 없습니다.\\n\\n우선 답변글부터 삭제하여 주십시오.');

// 코멘트 달린 원글의 삭제 여부
$sql = " select count(*) as cnt from $write_table
            where wr_parent = '$wr_id'
            and mb_id <> '{$member['mb_id']}'
            and wr_is_comment = 1 ";
$row = sql_fetch($sql);
if ($row['cnt'] >= $board['bo_count_delete'] && !$is_admin)
    alert('이 글과 관련된 코멘트가 존재하므로 삭제 할 수 없습니다.\\n\\n코멘트가 '.$board['bo_count_delete'].'건 이상 달린 원글은 삭제할 수 없습니다.');


// 사용자 코드 실행
@include_once($board_skin_path.'/delete.skin.php');

$act = isset($act) ? strip_tags($act) : '';
$count_chk_bo_table = (isset($_GET['bo_table']) && is_array($_GET['bo_table'])) ? count($_GET['bo_table']) : 0;


// 게시판 관리자 이상 복사, 이동 가능
// if ($is_admin != 'board' && $is_admin != 'group' && $is_admin != 'super')
//     alert_close('게시판 관리자 이상 접근이 가능합니다.');

// if ($sw != 'move' && $sw != 'copy')

//     alert('sw 값이 제대로 넘어오지 않았습니다.');

// if(! $count_chk_bo_table)
//     alert('게시물을 '.$act.'할 게시판을 한개 이상 선택해 주십시오.', $url);

// 원본 파일 디렉토리
$src_dir = G5_DATA_PATH.'/file/'.$bo_table;






$save = array();
$save_count_write = 0;
$save_count_comment = 0;
$cnt = 0;

$sw = 'move';

$sql = " select * from $write_table where wr_id in ({$wr_id}) order by wr_id ";
$result = sql_query($sql);
while ($row = sql_fetch_array($result))
{
   
    $save[$cnt]['wr_contents'] = array();

    $wr_num = $row['wr_num'];
    for ($i=0; $i<1; $i++)
    {
        $move_bo_table = $row['wr_1']; // 복구할 테이블명

        // 취약점 18-0075 참고
        $sql = "select * from {$g5['board_table']} where bo_table like '".sql_real_escape_string($move_bo_table)."' ";
        $move_board = sql_fetch($sql);
        // 존재하지 않다면
        
        if( !$move_board['bo_table'] ) continue;

        

        $move_write_table = $g5['write_prefix'] . $move_bo_table;

        $src_dir = G5_DATA_PATH.'/file/'.$bo_table; // 원본 디렉토리
        $dst_dir = G5_DATA_PATH.'/file/'.$move_bo_table; // 복사본 디렉토리

        $count_write = 0;
        $count_comment = 0;

        $next_wr_num = get_next_num($move_write_table);

        $sql2 = " select * from $write_table where wr_num = '$wr_num' order by wr_parent, wr_is_comment, wr_comment desc, wr_id ";
        $result2 = sql_query($sql2);
        while ($row2 = sql_fetch_array($result2))
        {
            $save[$cnt]['wr_contents'][] = $row2['wr_content'];

            $nick = cut_str($member['mb_nick'], $config['cf_cut_name']);
            if (!$row2['wr_is_comment'] && $config['cf_use_copy_log']) {
                if(strstr($row2['wr_option'], 'html')) {
                    $log_tag1 = '<div class="content_'.$sw.'">';
                    $log_tag2 = '</div>';
                } else {
                    $log_tag1 = "\n";
                    $log_tag2 = '';
                }

                //$row2['wr_content'] .= "\n".$log_tag1.'[이 게시물은 '.$nick.'님에 의해 '.G5_TIME_YMDHIS.' '.$board['bo_subject'].'에서 '.($sw == 'copy' ? '복사' : '삭제').' 되어 임시게시판에 저장됨]'.$log_tag2;
            }

            // 게시글 추천, 비추천수
            $wr_good = $wr_nogood = 0;
            if ($sw == 'move' && $i == 0) {
                $wr_good = $row2['wr_good'];
                $wr_nogood = $row2['wr_nogood'];
            }

            $sql = " insert into $move_write_table
                        set wr_num = '$next_wr_num',
                             wr_reply = '{$row2['wr_reply']}',
                             wr_is_comment = '{$row2['wr_is_comment']}',
                             wr_comment = '{$row2['wr_comment']}',
                             wr_comment_reply = '{$row2['wr_comment_reply']}',
                             ca_name = '".addslashes($row2['ca_name'])."',
                             wr_option = '{$row2['wr_option']}',
                             wr_subject = '".addslashes($row2['wr_subject'])."',
                             wr_content = '".addslashes($row2['wr_content'])."',
                             wr_link1 = '".addslashes($row2['wr_link1'])."',
                             wr_link2 = '".addslashes($row2['wr_link2'])."',
                             wr_link1_hit = '{$row2['wr_link1_hit']}',
                             wr_link2_hit = '{$row2['wr_link2_hit']}',
                             wr_hit = '{$row2['wr_hit']}',
                             wr_good = '{$wr_good}',
                             wr_nogood = '{$wr_nogood}',
                             mb_id = '{$row2['mb_id']}',
                             wr_password = '{$row2['wr_password']}',
                             wr_name = '".addslashes($row2['wr_name'])."',
                             wr_email = '".addslashes($row2['wr_email'])."',
                             wr_homepage = '".addslashes($row2['wr_homepage'])."',
                             wr_datetime = '{$row2['wr_datetime']}',
                             wr_file = '{$row2['wr_file']}',
                             wr_last = '{$row2['wr_last']}',
                             wr_ip = '{$row2['wr_ip']}',
                             wr_1 = '".$bo_table."',
                             wr_2 = '".addslashes($row2['wr_2'])."',
                             wr_3 = '".addslashes($row2['wr_3'])."',
                             wr_4 = '".addslashes($row2['wr_4'])."',
                             wr_5 = '".addslashes($row2['wr_5'])."',
                             wr_6 = '".addslashes($row2['wr_6'])."',
                             wr_7 = '".addslashes($row2['wr_7'])."',
                             wr_8 = '".addslashes($row2['wr_8'])."',
                             wr_9 = '".addslashes($row2['wr_9'])."',
                             wr_10 = '".addslashes($row2['wr_10'])."' ";
            
            sql_query($sql);
            
            $count_write++;

            $insert_id = sql_insert_id();

            // 코멘트가 아니라면
            if (!$row2['wr_is_comment'])
            {
                $save_parent = $insert_id;

                $sql3 = " select * from {$g5['board_file_table']} where bo_table = '$bo_table' and wr_id = '{$row2['wr_id']}' order by bf_no ";
                $result3 = sql_query($sql3);
                for ($k=0; $row3 = sql_fetch_array($result3); $k++)
                {
                    if ($row3['bf_file'])
                    {
                        // 원본파일을 복사하고 퍼미션을 변경
                        // 제이프로님 코드제안 적용
                        $copy_file_name = ($bo_table !== $move_bo_table) ? $row3['bf_file'] : $row2['wr_id'].'_copy_'.$insert_id.'_'.$row3['bf_file'];
                        $is_exist_file = is_file($src_dir.'/'.$row3['bf_file']) && file_exists($src_dir.'/'.$row3['bf_file']);
                        if( $is_exist_file ){
                            @copy($src_dir.'/'.$row3['bf_file'], $dst_dir.'/'.$copy_file_name);
                            @chmod($dst_dir.'/'.$row3['bf_file'], G5_FILE_PERMISSION);
                        }

                        $row3 = run_replace('bbs_move_update_file', $row3, $copy_file_name, $bo_table, $move_bo_table, $insert_id);
                    }

                    $sql = " insert into {$g5['board_file_table']}
                                set bo_table = '$move_bo_table',
                                     wr_id = '$insert_id',
                                     bf_no = '{$row3['bf_no']}',
                                     bf_source = '".addslashes($row3['bf_source'])."',
                                     bf_file = '$copy_file_name',
                                     bf_download = '{$row3['bf_download']}',
                                     bf_content = '".addslashes($row3['bf_content'])."',
                                     bf_fileurl = '".addslashes($row3['bf_fileurl'])."',
                                     bf_thumburl = '".addslashes($row3['bf_thumburl'])."',
                                     bf_storage = '".addslashes($row3['bf_storage'])."',
                                     bf_filesize = '{$row3['bf_filesize']}',
                                     bf_width = '{$row3['bf_width']}',
                                     bf_height = '{$row3['bf_height']}',
                                     bf_type = '{$row3['bf_type']}',
                                     bf_datetime = '{$row3['bf_datetime']}' ";
                    sql_query($sql);
                    
                    $count_comment++;

                    if ($sw == 'move' && $row3['bf_file'])
                        $save[$cnt]['bf_file'][$k] = $src_dir.'/'.$row3['bf_file'];
                }

                if ($sw == 'move' && $i == 0)
                {
                    // 스크랩 이동
                    sql_query(" update {$g5['scrap_table']} set bo_table = '$move_bo_table', wr_id = '$save_parent' where bo_table = '$bo_table' and wr_id = '{$row2['wr_id']}' ");

                    // 최신글 이동
                    sql_query(" update {$g5['board_new_table']} set bo_table = '$move_bo_table', wr_id = '$save_parent', wr_parent = '$save_parent' where bo_table = '$bo_table' and wr_id = '{$row2['wr_id']}' ");

                    // 추천데이터 이동
                    sql_query(" update {$g5['board_good_table']} set bo_table = '$move_bo_table', wr_id = '$save_parent' where bo_table = '$bo_table' and wr_id = '{$row2['wr_id']}' ");
                }
            }
            else
            {

                if ($sw == 'move')
                {
                    // 최신글 이동
                    sql_query(" update {$g5['board_new_table']} set bo_table = '$move_bo_table', wr_id = '$insert_id', wr_parent = '$save_parent' where bo_table = '$bo_table' and wr_id = '{$row2['wr_id']}' ");
                }
            }

            sql_query(" update $move_write_table set wr_parent = '$save_parent' where wr_id = '$insert_id' ");

            if ($sw == 'move')
                $save[$cnt]['wr_id'] = $row2['wr_parent'];

            $cnt++;
        }

        sql_query(" update {$g5['board_table']} set bo_count_write = bo_count_write + '$count_write' where bo_table = '$move_bo_table' ");
        sql_query(" update {$g5['board_table']} set bo_count_comment = bo_count_comment + '$count_comment' where bo_table = '$move_bo_table' ");
        
        run_event('bbs_move_copy', $row2, $move_bo_table, $insert_id, $next_wr_num, $sw);

        delete_cache_latest($move_bo_table);
    }

    $save_count_write += $count_write;
    $save_count_comment += $count_comment;
}

delete_cache_latest($bo_table);


// 나라오름님 수정 : 원글과 코멘트수가 정상적으로 업데이트 되지 않는 오류를 잡아 주셨습니다.
//$sql = " select wr_id, mb_id, wr_comment from $write_table where wr_parent = '$write[wr_id]' order by wr_id ";
$sql = " select wr_id, mb_id, wr_is_comment, wr_content from $write_table where wr_parent = '{$write['wr_id']}' order by wr_id ";
$result = sql_query($sql);
while ($row = sql_fetch_array($result))
{
    // 원글이라면
    if (!$row['wr_is_comment'])
    {
        // 원글 포인트 삭제
        if (!delete_point($row['mb_id'], $bo_table, $row['wr_id'], '쓰기'))
            insert_point($row['mb_id'], $board['bo_write_point'] * (-1), "{$board['bo_subject']} {$row['wr_id']} 글삭제");

        // 업로드된 파일이 있다면 파일삭제
        $sql2 = " select * from {$g5['board_file_table']} where bo_table = '$bo_table' and wr_id = '{$row['wr_id']}' ";
        $result2 = sql_query($sql2);
        while ($row2 = sql_fetch_array($result2)) {

            $delete_file = run_replace('delete_file_path', G5_DATA_PATH.'/file/'.$bo_table.'/'.str_replace('../', '', $row2['bf_file']), $row2);
            if( file_exists($delete_file) ){
                @unlink($delete_file);
            }
            // 썸네일삭제
            if(preg_match("/\.({$config['cf_image_extension']})$/i", $row2['bf_file'])) {
                delete_board_thumbnail($bo_table, $row2['bf_file']);
            }
        }

        // 에디터 썸네일 삭제
        delete_editor_thumbnail($row['wr_content']);

        // 파일테이블 행 삭제
        sql_query(" delete from {$g5['board_file_table']} where bo_table = '$bo_table' and wr_id = '{$row['wr_id']}' ");

    }
    else
    {
        // 코멘트 포인트 삭제
        if (!delete_point($row['mb_id'], $bo_table, $row['wr_id'], '댓글'))
            insert_point($row['mb_id'], $board['bo_comment_point'] * (-1), "{$board['bo_subject']} {$write['wr_id']}-{$row['wr_id']} 댓글삭제");


    }
}

// 게시글 삭제
sql_query(" delete from $write_table where wr_parent = '{$write['wr_id']}' ");

// 최근게시물 삭제
sql_query(" delete from {$g5['board_new_table']} where bo_table = '$bo_table' and wr_parent = '{$write['wr_id']}' ");

// 스크랩 삭제
sql_query(" delete from {$g5['scrap_table']} where bo_table = '$bo_table' and wr_id = '{$write['wr_id']}' ");


// 공지사항 삭제
$notice_array = explode("\n", trim($board['bo_notice']));
$bo_notice = "";
for ($k=0; $k<count($notice_array); $k++)
    if ((int)$write['wr_id'] != (int)$notice_array[$k])
        $bo_notice .= $notice_array[$k] . "\n";
$bo_notice = trim($bo_notice);

$bo_notice = board_notice($board['bo_notice'], $write['wr_id']);
sql_query(" update {$g5['board_table']} set bo_notice = '$bo_notice' where bo_table = '$bo_table' ");

// 글숫자 감소
if ($count_write > 0 || $count_comment > 0)
    sql_query(" update {$g5['board_table']} set bo_count_write = bo_count_write - '$count_write', bo_count_comment = bo_count_comment - '$count_comment' where bo_table = '$bo_table' ");
    


@include_once($board_skin_path.'/delete.tail.skin.php');

delete_cache_latest($bo_table);

run_event('bbs_delete', $write, $board);

goto_url(short_url_clean(G5_HTTP_BBS_URL.'/board.php?bo_table='.$bo_table.'&amp;page='.$page.$qstr));