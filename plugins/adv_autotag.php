<?php

fof_add_tag_prefilter ( 'fof_adv_autotag', 'fof_adv_autotag' );

function fof_adv_autotag ( $link, $title, $content )
{
	$tags = array();

    $prefs = fof_prefs();
    $autotag = empty($prefs['adv_autotag']) ? NULL : $prefs['adv_autotag'];

	if ( $autotag )
	{
		foreach ( $autotag as $pref )
		{
			$truth = false;

			switch ( $pref [ 'what' ] )
			{
				case 'item_title':
					$what = $title;
					break;

				case 'item_content':
					$what = $title . ' ' . $content;
					break;
			}

			switch ( $pref [ 'op' ] )
			{
				case 'is':
					$truth = ($what == $pref [ 'filter' ] );
					break;

				case 'contains':
					$truth = stristr ( $what, $pref [ 'filter' ] ) !== false;
					break;

				case 'regex':
					$truth = preg_match ( $pref [ 'filter' ], $what );
					break;
			}

			if ( $truth )
				$tags[] = $pref [ 'tag' ];
		}
	}

	return $tags;
}

function save_prefs_adv_autotag ( $post )
{
	if ( !isset ( $post [ 'aa_autotag' ] ) && !isset ( $post [ 'aa_apply_u' ] ) && !isset ( $post [ 'aa_apply_a' ] ) )
		return;

	global $prefs;

	foreach ( $post [ 'aa_pref' ] as $key => $val )
		if ( empty ( $val [ 'filter' ] ) || empty ( $val [ 'tag' ] ) )
			unset ( $post [ 'aa_pref' ][ $key ] );

	usort ( $post [ 'aa_pref' ], '_cb_prefsort' );

	$prefs -> set ( 'adv_autotag', array_values ( $post [ 'aa_pref' ] ) );
	$prefs -> save();

	// apply?
	if ( isset ( $post [ 'aa_apply_u' ] ) || isset ( $post [ 'aa_apply_a' ] ) )
	{
		if ( isset ( $post [ 'aa_apply_u' ] ) )
			$items = fof_get_items ( fof_current_user() );
		else
			$items = fof_get_items ( fof_current_user(), null, 'all' );

		foreach ( $items as $item )
		{
			$tags = fof_adv_autotag ( null, $item [ 'item_title' ], $item [ 'item_content' ] );

			if ( count ( $tags ) )
				fof_tag_item ( fof_current_user(), $item [ 'item_id' ], $tags );
		}
	}
}

function edit_prefs_adv_autotag ( $prefs )
{
	echo '<br><h1 id="adv_autotagging">Advanced Autotagging</h1>
		<div style="border: 1px solid black; margin: 10px; padding: 10px; font-size: 12px; font-family: verdana, arial;">
		<p><strong>Note:</strong> Instead of adding you can also remove a tag by using a dash as first character, use e.g. \'-unread\' to automatically mark items as read.</p>
		<form method="post" action="prefs.php#adv_autotagging">
		<table cellpadding=3 cellspacing=0>
		<tr>
			<th>What</th>
			<th>Operator</th>
			<th>Filter</th>
			<th>Tag</th>
		</tr>';

	$aa_prefs = $prefs -> get ( 'adv_autotag' );
	if (empty($aa_prefs))
	    $aa_prefs = array();

	$count = 0;

	foreach ( $aa_prefs as $p )
	{
		echo '<tr>
			<td>
				<select name="aa_pref[' . $count . '][what]">
					<option value="item_content"' . ($p['what'] == 'item_content' ? ' selected="selected"' : '') . '>Post Title or Content</option>
					<option value="item_title"'   . ($p['what'] == 'item_title'   ? ' selected="selected"' : '') . '>Post Title</option>
				</select>
			</td>
			<td>
				<select name="aa_pref[' . $count . '][op]">
					<option value="contains"' . ($p['op'] == 'contains' ? ' selected="selected"' : '') . '>contains</option>
					<option value="is"'       . ($p['op'] == 'is'       ? ' selected="selected"' : '') . '>is</option>
					<option value="regex"'    . ($p['op'] == 'regex'    ? ' selected="selected"' : '') . '>regex</option>
				</select>
			</td>
			<td>
				<input type="text" name="aa_pref[' . $count . '][filter]" size="40" value="' . htmlspecialchars($p['filter']) . '" />
			</td>
			<td>
				<input type="text" name="aa_pref[' . $count . '][tag]" value="' . htmlspecialchars($p['tag']) . '" />
			</td>
		</tr>';

		$count++;
	}

	echo '<tr>
			<td>
				<select name="aa_pref[' . $count . '][what]">
					<option value="item_content">Post Title or Content</option>
					<option value="item_title">Post Title</option>
				</select>
			</td>
			<td>
				<select name="aa_pref[' . $count . '][op]">
					<option value="contains">contains</option>
					<option value="is">is</option>
					<option value="regex">regex</option>
				</select>
			</td>
			<td>
				<input type="text" name="aa_pref[' . $count . '][filter]" size="40" value="" />
			</td>
			<td>
				<input type="text" name="aa_pref[' . $count . '][tag]" value="" />
			</td>
		</tr>
	</table>
	<br>
	<input type="submit" name="aa_autotag" value="Save Advanced Autotagging Preferences" />
	<input type="submit" name="aa_apply_u"   value="Apply Settings on Unread Posts" />
	<input type="submit" name="aa_apply_a"   value="Apply Settings on All Posts" />
	</form>
	</div>';
}


function _cb_prefsort ( $a, $b )
{
	if ( $a [ 'tag' ] == $b [ 'tag' ] )
		return strcmp ( $a [ 'filter' ], $b [ 'filter' ] );

	return strcmp ( $a [ 'tag' ], $b [ 'tag' ] );
}