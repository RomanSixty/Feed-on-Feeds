<?php

include_once("fof-main.php");
include_once("fof-render.php");

if($_GET['how'] == 'paged' && !isset($_GET['which']))
{
	$which = 0;
}
else
{
	$which = $_GET['which'];
}

$order = $_GET['order'];

if(!isset($_GET['what']))
{
    $what = "unread";
}
else
{
    $what = $_GET['what'];
}

if(!isset($_GET['order']))
{
	$order = $fof_user_prefs["order"];
}

$how = $_GET['how'];
$feed = $_GET['feed'];
$when = $_GET['when'];
$howmany = $_GET['howmany'];

$title = fof_view_title($_GET['feed'], $what, $_GET['when'], $which, $_GET['howmany'], $_GET['search']);
$noedit = $_GET['noedit'];

?>

<?php // print_r(fof_prefs()); ?>

<?php

//$junk = "blahdeblah";
//fof_db_save_prefs($fof_user_id, $junk);

?>

<p><?php echo $title?> -
<?php

if($order == "desc")
{
echo '[new to old] ';
echo "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=asc\">[old to new]</a>";
}
else
{
echo "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=desc\">[new to old]</a>";
echo ' [old to new]';
}

?>
</p>

<!-- close this form to fix first item! -->

		<form id="itemform" name="items" action="view-action.php" method="post" onSubmit="return false;">
		<input type="hidden" name="action" />
		<input type="hidden" name="return" />

<?php
	$links = fof_get_nav_links($_GET['feed'], $what, $_GET['when'], $which, $_GET['howmany']);

	if($links)
	{
?>
		<center><?php echo $links ?></center>

<?php
	}


$result = fof_get_items(fof_current_user(), $_GET['feed'], $what, $_GET['when'], $which, $_GET['howmany'], $order, $_GET['search']);

$first = true;

foreach($result as $row)
{
	$item_id = $row['item_id'];
	if($first) print "<script>firstItem = 'i$item_id'; </script>";
	$first = false;
	print '<div class="item shown" id="i' . $item_id . '">';
	fof_render_item($row);
	print '</div>';

}

if(count($result) == 0)
{
	echo "<p><i>No items found.</i></p>";
}

?>
		</form>
