<?php

// PLUGIN MESSAGES

// Plugin description
	define('_DESCRIPTION',			'NucleusCMSで作成したwebサイトのURLを静的リンクとして生成します<br />詳しくはヘルプページを参照してください');

// Plugin Options
// Global
	define('_OP_ARCHIVE_DIR_NAME',	'アーカイブディレクトリへの Path の名前');
	define('_OP_ARCHIVES_DIR_NAME',	'アーカイブリストディレクトリへの Path の名前');
	define('_OP_MEMBER_DIR_NAME',	'メンバーディレクトリへの Path の名前');
	define('_OP_DEF_ITEM_KEY',		'新規作成時のアイテムのURIの接頭語');
	define('_OP_DEF_CAT_KEY',		'新規作成時のカテゴリーのURIの接頭語');
	define('_OP_DEF_SCAT_KEY',		'新規作成時のサブカテゴリーのURIの接頭語');
	define('_OP_INCLUDE_CBNAME',	'デフォルトのブログの URL にもブログの短縮名を追加する');
	define('_OP_NOT_FOUND',			'URI に該当するデータが存在しなかった場合の挙動');
	define('_OP_TABLE_DELETE',		'アンインストールするときにテーブルを削除しますか？');
	define('_OP_QUICK_LINK',		'クイックメニューにショートカットを追加しますか？');
// Blog
	define('_OP_USE_CURL',			'このブログで URI の別名を使用する');
	define('_OP_BLOG_PATH',			'このブログのリクエスト URI');
	define('_OP_RED_NORM',			'ノーマルの URL でアクセスがあった時にリダイレクトしますか？');
	define('_OP_RED_SEARCH',		'検索の時の URL を 「/search/検索語」にリダイレクトしますか？');
// Item
	define('_OP_ITEM_PATH',			'このアイテムのリクエスト URI');
// Category
	define('_OP_CATEGORY_PATH',		'このカテゴリーのリクエスト URI');
// Member
	define('_OP_MEMBER_PATH',		'このメンバーのリクエスト URI');

// Error messages
	define('_NO_SUCH_URI',			'リクエストされた URI にアクセスできませんでした<br />もう一度 URI をよく確かめてください<br />');
	define('_INVALID_ERROR',		'<h2>パスが不正です</h2>');
	define('_INVALID_MSG',			'パスに不正な文字列が指定されました<br />使用できる文字は、[アルファベット/数字/-(ハイフン)/_(アンダーバー)]のみ(すべて半角)です<br />また、末尾に[.html]等の拡張子をつけることは出来ません');
	define('_CONFLICT_ERROR',		'<h2>パスが重複しています</h2>');
	define('_CONFLICT_MSG',			'同一ブログ内にすでに存在するパスが指定されたので、パスの末尾に ID が付加されました<br />パスを変更する場合は、再度編集してください');
	define('_DELETE_PATH',			'<h2>エイリアス名を削除しました</h2>');
	define('_DELETE_MSG',			'パスとして空白が指定されたため、登録済みのパスを削除しました<br />リンク URI には通常の FancyURLs のものが適用されます');
//	define('_NOT_VALID_BLOG',		'指定されたブログは存在しません');
//	define('_NOT_VALID_ITEM',		'指定されたアイテムは存在しません');
//	define('_NOT_VALID_CAT',		'指定されたカテゴリーは存在しません');
//	define('_NOT_VALID_SUBCAT',		'指定されたサブカテゴリーは存在しません');
//	define('_NOT_VALID_MEMBER',		'指定されたメンバーは存在しません');
	define('_NOT_VALID_BLOG',		'');
	define('_NOT_VALID_ITEM',		'');
	define('_NOT_VALID_CAT',		'');
	define('_NOT_VALID_SUBCAT',		'');
	define('_NOT_VALID_MEMBER',		'');

// Admin area
	define('_ADMIN_TITLE',			'URL CUSTOMIZE');
	define('_QUICK_TIPS',			'リンク表示用 URI の管理');
	define('_ERROR_DISALLOWED',		'アクセスできません');
	define('_DISALLOWED_MSG',		'ログインしていないか、または管理者権限がありません');
	define('_ADMIN_AREA_TITLE',		'リンク表示用 URI の管理ページ');
	define('_OPTION_SETTING',		'このプラグインのオプション設定ページへ移動');
	define('_FOR_ITEMS_SETTING',	'アイテム用 URI 管理ページへ');
	define('_FOR_MEMBER_SETTING',	'メンバー用 URI 管理ページへ');
	define('_FOR_CATEGORY_SETTING',	'カテゴリー用 URI 管理ページへ');
	define('_FOR_BLOG_SETTING',		'ブログ用 URI 管理ページへ');
	define('_EDIT',					'編集');
	define('_BLOG_LIST_TITLE',		'ブログ名');
	define('_BLOG_URI_SETTING',		'ブログ用アクセスパス管理');
	define('_BLOG_URI_NAME',		'ブログ用アクセスパス');
	define('_BLOG_SETTING',			'ブログの設定の編集');
	define('_ITEM_SETTING',			'アイテムの設定の編集');
	define('_CATEGORY_SETTING',		'カテゴリーの設定の編集');
	define('_SUBCATEGORY_SETTING',	'サブカテゴリーの設定の編集');
	define('_MEMBER_SETTING',		'メンバーの設定の編集');
	define('_LISTS_CAT_NAME',		'カテゴリ名/サブカテゴリ名');
	define('_LISTS_ITEM_DESC',		'本文の冒頭');
	define('_LISTS_PATH',			'アクセス パス');
	define('_UPDATE_SUCCESS',		'エイリアス名を更新しました');

?>
