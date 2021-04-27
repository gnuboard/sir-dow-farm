<?php

add_event('member_login_check', 'trash_check', 1, 3);

function trash_check($mb, $link, $is_social_login){
    global $g5;

    // 관리자 로그인이면
    if( is_admin($mb['mb_id']) ) {

        include_once('./_common.php');
        //실행문
        $delete_Date = date("Y-m-d h:i:s", strtotime("-7 Day")); // 일주일이 지난 게시글 삭제
        
        $trash_table = G5_TABLE_PREFIX.'write_trash';
        $bo_table = 'trash';
        $sql = " select wr_id, mb_id, wr_is_comment, wr_content from $trash_table where wr_2 >= '".$delete_Date."'  order by wr_id ";
 
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

                $count_write++;

            }
            else
            {
                // 코멘트 포인트 삭제
                if (!delete_point($row['mb_id'], $bo_table, $row['wr_id'], '댓글'))
                    insert_point($row['mb_id'], $board['bo_comment_point'] * (-1), "{$board['bo_subject']} {$row['wr_id']}-{$row['wr_id']} 댓글삭제");

                $count_comment++;

            }
            // 게시글 삭제
            sql_query(" delete from $trash_table where wr_parent = '{$row['wr_id']}' ");

            // 최근게시물 삭제
            sql_query(" delete from {$g5['board_new_table']} where bo_table = '$bo_table' and wr_parent = '{$row['wr_id']}' ");
    
            // 스크랩 삭제
            sql_query(" delete from {$g5['scrap_table']} where bo_table = '$bo_table' and wr_id = '{$row['wr_id']}' ");

            
            $bo_notice = board_notice($board['bo_notice'], $write['wr_id']);
            sql_query(" update {$g5['board_table']} set bo_notice = '$bo_notice' where bo_table = '$bo_table' ");

            // 글숫자 감소
            if ($count_write > 0 || $count_comment > 0)
            sql_query(" update {$g5['board_table']} set bo_count_write = bo_count_write - '$count_write', bo_count_comment = bo_count_comment - '$count_comment' where bo_table = '$bo_table' ");

            delete_cache_latest($bo_table);
    
        }
        
    }
}