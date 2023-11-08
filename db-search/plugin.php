<?php

class pluginDbSearch extends Plugin
{
	/**
	 * @var qdb
	 * @access private
	 */	
	private $qdb;
	
	/**
	 * @var dbFile
	 * @access private
	 */	
	private $dbFile;
	
	/**
	 * @var pagesFound
	 * @access private
	 */	
	private $pagesFound = array();
	
	/**
	 * @var numberOfItems
	 * @access private
	 */	
	private $numberOfItems = 0;
	
	/**
	 * @var hook
	 * @access private
	 */	
	private $hook = 'search';
	
	public function init()
	{
		// Fields and default values for the database of this plugin
		$this->dbFields = array(
			'label'=>'Search',
			'minChars'=>3,
			'dbuuid'=>null,
			'showButtonSearch'=>false
		);
	}

	public function form()
	{
		global $L;
		
		$html  = '';
		
		if ( !class_exists( 'SQLite3' ) )
		{
			$html .= '<div class="alert alert-warning" role="alert">';
			$html .= $L->g('sqlite-not-supported');
			$html .= '</div>';
		}
		
		else
		{
			$html .= '<div class="alert alert-primary" role="alert">';
			$html .= $this->description();
			$html .= '</div>';

			$html .= '<div>';
			$html .= '<label>'.$L->get('Label').'</label>';
			$html .= '<input name="label" type="text" value="'.$this->getValue('label').'">';
			$html .= '<span class="tip">'.$L->get('This title is almost always used in the sidebar of the site').'</span>';
			$html .= '</div>';

			$html .= '<div>';
			$html .= '<label>'.$L->get('Minimum number of characters when searching').'</label>';
			$html .= '<input name="minChars" type="text" value="'.$this->getValue('minChars').'">';
			$html .= '</div>';

					$html .= '<div>';
					$html .= '<label>'.$L->get('Show button search').'</label>';
					$html .= '<select name="showButtonSearch">';
					$html .= '<option value="true" '.($this->getValue('showButtonSearch')===true?'selected':'').'>'.$L->get('enabled').'</option>';
					$html .= '<option value="false" '.($this->getValue('showButtonSearch')===false?'selected':'').'>'.$L->get('disabled').'</option>';
			$html .= '</select>';
					$html .= '</div>';
			$html .= '<div>';
		}

		return $html;
	}

	// HTML for sidebar
	public function siteSidebar()
	{
		global $L;

		$html  = '<div class="plugin plugin-search">';
		$html .= '<h2 class="plugin-label">'.$this->getValue('label').'</h2>';
		$html .= '<div class="plugin-content">';
		$html .= '<input type="text" id="jspluginSearchText" /> ';
		if ($this->getValue('showButtonSearch')) {
			$html .= '<input type="button" value="'.$L->get('Search').'" onClick="pluginSearch()" />';
		}
		$html .= '</div>';
		$html .= '</div>';

		$DOMAIN_BASE = DOMAIN_BASE;
$html .= <<<EOF
<script>
	function pluginSearch() {
		var text = document.getElementById("jspluginSearchText").value;
		window.open('$DOMAIN_BASE'+'search/'+text, '_self');
		return false;
	}

	document.getElementById("jspluginSearchText").onkeypress = function(e) {
		if (!e) e = window.event;
		var keyCode = e.keyCode || e.which;
		if (keyCode == '13'){
			pluginSearch();
			return false;
		}
	}
</script>
EOF;

		return $html;
	}
	
	public function loadDb()
	{
		if ( is_null( $this->qdb ) && class_exists( 'SQLite3' ) && !empty( $this->getValue('dbuuid') ) )
		{
			$this->dbFile = $this->workspace() . 'db_' . $this->getValue('dbuuid') . '.sqlite';

			$this->qdb = new SQLite3( $this->dbFile, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
		}
	}

	public function install( $position = 0 )
	{
		parent::install( $position );
		
		$uuid = $this->generateRand();
		
		$this->setField( 'dbuuid', $uuid );
		
		$this->dbFile = $this->dbFile();
		
		$this->qdb = new SQLite3( $this->dbFile, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
		
		$this->qdb->query(
			'CREATE TABLE IF NOT EXISTS "pages" (
				"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
				"uuid" VARCHAR,
				"sef" VARCHAR,
				"title" TEXT,
				"description" TEXT,
				"content" TEXT,
				"image" VARCHAR,
				"date" TEXT
			)'
		);

		return $this->createCache();
	}

	// Method called when the user click on button save in the settings of the plugin
	public function post()
	{
		parent::post();
		return $this->createCache();
	}

	public function afterPageCreate()
	{
		$this->createCache();
	}

	public function afterPageModify()
	{
		$this->createCache();
	}

	public function afterPageDelete()
	{
		$this->createCache();
	}
	
	public function beforeAll()
	{
		$this->loadDb();
		
		if ( is_null( $this->qdb ) )
		{
			return;
		}
		
		// Check if the URL match with the webhook		
		if ( $this->webhook( $this->hook, false, false ) )
		{
			global $site;
			global $url;

			// Change the whereAmI to avoid load pages in the rule 69.pages
			// This is only for performance purpose
			$url->setWhereAmI( $this->hook );

			// Get the string to search from the URL
			$stringToSearch = $this->webhook( $this->hook, true, false );
			$stringToSearch = trim( $stringToSearch, '/' );

			// Search the string in the db and get all pages with matches
			$list = $this->search( $stringToSearch );
			
			if ( empty( $list ) )
			{
				return;
			}
			
			$this->numberOfItems = count( $list );

			// Split the content in pages
			$pageNumber = ( !empty( $url->pageNumber() ) ? $url->pageNumber() : 1 );
			$itemsPerPage = (int) $site->itemsPerPage();
			
			if( $itemsPerPage <= $this->numberOfItems )
			{
				$this->pagesFound = $list;
			}
			
			else
			{
				$from = ( ( $pageNumber * $itemsPerPage ) - $itemsPerPage );
				
				$this->pagesFound = array_slice( $list, $from, $itemsPerPage, true );
			}
		}
	}

	public function paginator()
	{
		if ( $this->webhook( $this->hook, false, false ) )
		{
			// Get the pre-defined variable from the rule 99.paginator.php
			// Is necessary to change this variable to fit the paginator with the result from the search
			global $numberOfItems;
			
			$numberOfItems = $this->numberOfItems;
		}
	}

	public function beforeSiteLoad()
	{
		if ( is_null( $this->qdb ) )
		{
			return;
		}
		
		if ( $this->webhook( $this->hook, false, false ) )
		{
			global $url;
			global $WHERE_AM_I;
			$WHERE_AM_I = $this->hook;

			// Get the pre-defined variable from the rule 69.pages.php
			// We change the content to show in the website
			global $content;
			
			$content = array();
			
			if ( empty( $this->pagesFound ) )
			{
				return;
			}
			
			foreach ( $this->pagesFound as $pageKey )
			{
				try
				{
					$page = new Page( $pageKey );
					array_push( $content, $page );
				}
				catch (Exception $e) {
					// continue
				}
			}
		}
	}
	
	// Search inside the database
	// Returns an array with the results related to the ;text
	private function search( $text )
	{
		$statement = $this->qdb->prepare('SELECT sef FROM "pages" WHERE "title" LIKE ? OR "content" LIKE ?');
		$statement->bindValue( 1, '%' . $text . '%' );
		$statement->bindValue( 2, '%' . $text . '%' );
		$result = $statement->execute();

		$data = array();
		
		while( $r = $result->fetchArray( SQLITE3_ASSOC ) )
		{
			$data[] = $r['sef'];
		}
		
		$result->finalize();
		
		return $data;
	}
	
	private function generateRand()
	{
		$string = '1234567890qwertyuioplkjhgfdsazxcvbnm';
		
		return substr( str_shuffle( $string ), 0, 8 );
	}
	
	// Generate the DB file
	// This function is necessary to call it when you create, edit or remove content
	private function createCache()
	{
		global $pages;
		
		$this->loadDb();
		
		if ( is_null( $this->qdb ) )
		{
			return;
		}
		
		//We are going to delete everything.
		//This is a simple way so we can remove deleted pages from the DB.
		$this->qdb->exec('DELETE FROM pages;');
		$this->qdb->exec('UPDATE SQLITE_SEQUENCE SET seq = 0 WHERE name = "pages";');

		// Get all pages published
		$list = $pages->getList( 1, -1, true, true, true, false, false );
		
		if ( empty( $list ) )
		{
			return;
		}
		
		foreach ( $list as $pageKey )
		{
			$page = buildPage( $pageKey );

			$add = $this->qdb->prepare('INSERT INTO "pages"
			("uuid", "sef", "title", "description", "content", "image", "date")
			VALUES
			(:uuid, :sef, :title, :description, :content, :image, :date)');

			$add->bindValue(':uuid', 		$page->uuid() );
			$add->bindValue(':sef', 		$pageKey );
			$add->bindValue(':title', 		$page->title() );
			$add->bindValue(':description', $page->description() );
			$add->bindValue(':content', 	$page->content() );
			$add->bindValue(':image', 		$page->coverImage() );
			$add->bindValue(':date', 		$page->dateRaw() );

			$add->execute();
		}
	}

	// Returns the absolute path of the db file
	private function dbFile()
	{
		return $this->workspace() . 'db_' . $this->getValue('dbuuid') . '.sqlite';
	}
}
