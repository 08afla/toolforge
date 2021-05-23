<!DOCTYPE html>
<?php
$username = '';
$error = '';

$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$nfi = new \NumberFormatter('fa-IR', \NumberFormatter::IGNORE);
$nfd = new \NumberFormatter('fa-IR', \NumberFormatter::DECIMAL);

if ( isset( $_POST['username'] ) ) {
  $username = str_replace('"', '', $_POST['username']);
  $prefix = '/^(User:|کاربر:)/';
  $username = preg_replace($prefix, '', $username);
  $username = ucfirst(trim($username));
  



//
#######################################################################
#	Configuration													  #
#######################################################################
$ip_parts   = $$username; #as int 192.51.181.1 or 2001:db8::3815
$begin_date = '20210420';
$end_date   = '20210425';
$max_record = 31;
$namespace  = 0; # for (main) article namespace use 0

#######################################################################
#	Pre-Execution													  #
#######################################################################
$SQL_SELECT_LIMIT = max_record;
$ip_search = CONCAT('%', @ip_parts, '%');

#######################################################################
#	Execution														  #
#######################################################################
$stmt='SELECT a.actor_name as user,
	   p.page_title as page,
       r.rev_timestamp as timestamp,
	   c.comment_text  as summary
	FROM actor a										#Actor
	INNER JOIN revision r 								#Revision
      ON  r.rev_actor=a.actor_id
      AND a.actor_user IS NULL 							#NULL for IP
      AND r.rev_timestamp >= @begin_date 
      AND r.rev_timestamp <= @end_date
      AND a.actor_name like @ip_search
	INNER JOIN  page p									#Page
      ON  r.rev_page=p.page_id	
      AND p.page_namespace = @namespace					
	INNER JOIN  comment c								#Comment
on c.comment_id=r.rev_comment_id'




//


  $mysqli = new mysqli('fawiki.analytics.db.svc.eqiad.wmflabs', $ts_mycnf['user'], $ts_mycnf['password'], 'fawiki_p');
  $q = $mysqli->prepare($stmt);
  $q->bind_param("sssss",  $ip_search  ,$begin_date,$end_date  ,$SQL_SELECT_LIMIT,$namespace );
  $q->execute();
  $q->bind_result($actor_id, $actor_name);
  $q->fetch();
  if($username === '') {
    $error = 'No IP Portions provided';
  }
  elseif(!isset($actor_id)) {
    $error = 'User does not exist!';
  } else {
    // Criterion 1
    unset($q);
    $q = $mysqli->prepare("SELECT MIN(log_timestamp) FROM logging_userindex WHERE log_type = 'newusers' AND log_action IN ('create', 'autocreate') AND log_actor=?");
    $q->bind_param("i", $actor_id);
    $q->execute();
    $q->bind_result($mints);
    $q->fetch();
    if(!isset($mints)) {
      // Try estimating account creation time using edits instead
      unset($q);
      $q = $mysqli->prepare('SELECT MIN(rev_timestamp) FROM revision_userindex WHERE rev_actor=?');
      $q->bind_param("i", $actor_id);
      $q->execute();
      $q->bind_result($mints);
      $q->fetch();
    }
    if(isset($mints) && strlen($mints) == 14){
      $year = $nfi->format(substr($mints, 0, 4));
      $month = $nfi->format(intval(substr($mints, 4, 2)));
      $day = $nfi->format(intval(substr($mints, 6, 2)));

      $mints =  $year . '٫' . $month . '٫' . $day;
    }
    // Criterion 2
    unset($q);
    $q = $mysqli->prepare("SELECT COUNT(*) FROM revision_userindex JOIN page ON page_id = rev_page AND page_namespace = 0 WHERE rev_timestamp < 20201012000000 AND rev_actor=?");
    $q->bind_param("i", $actor_id);
    $q->execute();
    $q->bind_result($edits);
    $q->fetch();
    $edits = $nfd->format($edits);
    // Criterion 3
    unset($q);
    $q = $mysqli->prepare("SELECT COUNT(*) FROM revision_userindex JOIN page ON page_id = rev_page AND page_namespace = 0 WHERE rev_timestamp < 20201012000000 AND rev_timestamp > 20191012000000 AND rev_actor=?");
    $q->bind_param("i", $actor_id);
    $q->execute();
    $q->bind_result($recentedits);
    $q->fetch();
    $recentedits = $nfd->format($recentedits);
  }
}
?>
<html lang="fa">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
  <meta content="" name="description">
  <meta content="" name="author">
  <link href="" rel="icon">
  <title>ارزیابی رأی‌مندی انتخابات هیئت نظارت</title>
  <link href="https://cdn.rtlcss.com/bootstrap/v4.2.1/css/bootstrap.min.css" rel="stylesheet">
</head>
<body dir="rtl" style="direction:rtl">
  <div id="wrapper">
    <div id="page-content-wrapper">
      <nav class="navbar navbar-expand-lg navbar-dark bg-secondary border-bottom">
        <div class="container">
          <a class="navbar-brand" href="./">ابزارهای آلفا۸۰</a>
          <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
              <li class="nav-item active">
                <a class="nav-link disabled" href="#">&larr; مشارکت‌های آی‌پی</a>
              </li>
            </ul>
          </div>
        </div>
      </nav>
      <div class="container">
        <h1 class="mt-4">جستجوی مشارکت‌های آی‌پی</h1>
        <p>این ابزار به شما کمک می‌کند که با دانستن تنها بخشی از آدرس آی‌پی مشارکت‌های وی را بررسی کنید </p>
        <form action="./ipcontrib.php" method="post">
          <div class="form-group">
            <label for="username">نام کاربری</label> <input aria-describedby="usernameHelp" class="form-control" id=
            "username" name="username" placeholder="بخشی از آدرس آی‌پی را وارد کنید" type="text" value=
            "<?php echo $username; ?>"> <small class="form-text text-muted" id="usernameHelp">نیازی به پیشوند «کاربر:»
            نیست.</small>
          </div><button class="btn btn-primary" type="submit">ارسال</button>
        </form>
        <hr>
        <?php if($error !== ''): ?>
        <div class="card mb-12 bg-danger">
          <div class="card-header text-white">
            خطا
          </div>
          <div class="card-body bg-white">
            <h5 class="card-title">درخواست شما شکست خورد</h5>
            <div class="card-text">
              <?php echo $error; ?>
            </div>
          </div>
        </div><?php elseif(isset($mints)): ?>
        <div class="card mb-12 bg-success">
          <div class="card-header text-white">
            نتایج
          </div>
          <div class="card-body bg-white">
            <h5 class="card-title">ارزیابی شرایط رأی‌مندی</h5>
            <div class="card-text">
              <p>این اطلاعات برای حساب <bdi><?php echo $username; ?></bdi> به دست آمد:</p>
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>پرسمان</th>
                    <th>نتیجه</th>
                    <th>شرط لازم برای رأی‌مندان</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>تاریخ ایجاد حساب</td>
                    <td><?php echo $mints; ?></td>
                    <td>پیش از ۲۰۲۰٫۷٫۱۲</td>
                  </tr>
                  <tr>
                    <td>ویرایش در مقاله‌ها پیش از شروع انتخابات</td>
                    <td><?php echo $edits; ?></td>
                    <td>دست کم ۵۰۰</td>
                  </tr>
                  <tr>
                    <td>ویرایش در مقاله‌ها در یک سال منتهی به انتخابات</td>
                    <td><?php echo $recentedits; ?></td>
                    <td>دست کم ۱۰۰</td>
                  </tr>
                </tbody>
              </table>
              <p>برای دیدن سیاههٔ قطع دسترسی این کاربر <a href=
              "https://fa.wikipedia.org/w/index.php?title=Special:Logs&page=User:<?php echo urlencode($username); ?>&type=block">
              اینجا</a> کلیک کنید.</p>
            </div>
          </div>
        </div><?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
