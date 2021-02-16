<?php
include_once('./_common.php');
if (!$is_admin) alert('관리자만 실행할 수 있습니다.');

$g5['title'] = 'DB 추가';

if ($_POST['setup']) {

    sql_query("
        ALTER TABLE `{$g5['member_table']}`
            ADD  `mb_od_count` SMALLINT NOT NULL
    ");

    echo '<p>회원 컬럼 추가 완료</p>';

    sql_query("
        ALTER TABLE `{$g5['g5_shop_item_use_table']}`
            ADD  `od_count` SMALLINT NOT NULL
    ");

    echo '<p>사용후기 컬럼 추가 완료</p>';

    echo '<a href="'.G5_SHOP_URL.'" id="btn">메인으로 돌아가기</a>';

}
?>

<style>
#btn {display:inline-block;margin:0;padding:8px;border:0;background:#e7e7e7;color:#860cff;text-decoration:underline;cursor:pointer}
</style>

<?php if (!$_POST['setup']) { ?>
<p>실행 버튼을 누르시면 기본 설정이 실행됩니다.</p>

<form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="post">
    <input type="hidden" name="setup" id="setup" value="1">
    <input type="submit" value="실행" id="btn">
</form>
<?php } ?>