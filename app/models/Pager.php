<?php

namespace Core;

defined('ROOT') or die("Direct script access denied");

/**
 * Pager class
 */
class Pager
{
	
	public $links 		= [];
	public $limit 		= 10;
	public $offset 		= 0;
	public $start 		= 1;
	public $end 		= 1;
	public $page_number = 1;
	public $total_pages = 0;
	public $window_radius = 3;

	public function __construct(int $limit = 10, $extras = 3)
	{
		$page_number = !empty($_GET['page']) ? (int)$_GET['page'] : 1;
		$page_number = $page_number < 1 ? 1 : $page_number;

		$this->start = $page_number - $extras;
		$this->end = $page_number + $extras;
		$this->start = $this->start < 1 ? 1 : $this->start;

		$this->page_number = $page_number;
		$this->window_radius = max(1, (int)$extras);
		$this->offset = ($page_number - 1) * $limit;

		$url = $_GET['url'] ?? 'home';
		$query_string = str_replace("url=","",$_SERVER['QUERY_STRING']);
		$current_link = ROOT . '/'.$url . '?' . trim(str_replace($url, "", $query_string),'&');
		if(!strstr($current_link, "page="))
			$current_link .= '&page='.$page_number;

		$first_link = preg_replace("/page=[0-9]+/", "page=1", $current_link);
		$next_link = preg_replace("/page=[0-9]+/", "page=".($page_number + $extras + 1), $current_link);
		
		$this->links['current'] 	= $current_link;
		$this->links['first'] 		= $first_link;
		$this->links['next']	 	= $next_link;

	}

	protected function pageUrl(int $page): string
	{
		$page = $page < 1 ? 1 : $page;
		return preg_replace("/page=[0-9]+/", "page=".$page, $this->links['current']);
	}

	protected function getVisiblePages(): array
	{
		if ($this->total_pages > 0) {
			$start = max(2, $this->page_number - $this->window_radius);
			$end = min($this->total_pages - 1, $this->page_number + $this->window_radius);
			if ($start > $end) {
				return [];
			}

			return range($start, $end);
		}

		$start = max(1, (int)$this->start);
		$end = max($start, (int)$this->end);
		return range($start, $end);
	}

	protected function renderPagination(): string
	{
		$visiblePages = $this->getVisiblePages();
		$totalPages = (int)$this->total_pages;
		$prevPage = max(1, $this->page_number - 1);
		$nextPage = $totalPages > 0 ? min($totalPages, $this->page_number + 1) : $this->page_number + 1;

		ob_start();
		?>
		<nav aria-label="Page navigation example">
		  <ul class="pagination">
		    <li class="page-item<?=$this->page_number <= 1 ? ' disabled' : ''?>">
		    	<a class="page-link" href="<?=$this->pageUrl(1)?>">First</a>
		    </li>
		    <li class="page-item<?=$this->page_number <= 1 ? ' disabled' : ''?>">
		    	<a class="page-link" href="<?=$this->pageUrl($prevPage)?>">Prev</a>
		    </li>

		    <?php foreach($visiblePages as $index => $page): ?>
		    	<?php if ($totalPages > 0 && $index === 0 && $page > 2): ?>
		    		<li class="page-item disabled"><span class="page-link">...</span></li>
		    	<?php endif; ?>
			    <li class="page-item <?=$page == $this->page_number ? 'active':''?>">
			    	<a class="page-link" href="<?=$this->pageUrl($page)?>"><?=$page?></a>
			    </li>
			    <?php if ($totalPages > 0 && $index === count($visiblePages) - 1 && $page < ($totalPages - 1)): ?>
			    	<li class="page-item disabled"><span class="page-link">...</span></li>
			    <?php endif; ?>
			<?php endforeach?>

		    <li class="page-item<?=($totalPages > 0 && $this->page_number >= $totalPages) ? ' disabled' : ''?>">
		    	<a class="page-link" href="<?=$this->pageUrl($nextPage)?>">Next</a>
		    </li>
		    <?php if ($totalPages > 0): ?>
		    <li class="page-item<?=$this->page_number >= $totalPages ? ' disabled' : ''?>">
		    	<a class="page-link" href="<?=$this->pageUrl($totalPages)?>">Last</a>
		    </li>
		    <?php endif; ?>
		  </ul>
		</nav>
		<?php
		return (string)ob_get_clean();
	}

	public function display()
	{
		echo $this->renderPagination();
	}

	public function displayTailwind():string
	{
		return $this->renderPagination();
	}

	public function displayCustom():string
	{
		return $this->renderPagination();
	}

	
}
