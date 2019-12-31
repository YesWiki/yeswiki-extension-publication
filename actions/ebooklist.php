<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Florian Schmitt <florian@outils-reseaux.org>                                      |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
//
/**
*
* Liste de toutes les pages Ebook
*
*
*@package publication
*
*@author        Florian Schmitt <florian@outils-reseaux.org>
*
*@copyright     Outils-Reseaux 2012
*@version       $Revision: 0.1 $ $Date: 2010/03/04 14:19:03 $
*/

if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}


$ebookPageNamePrefix = $this->getParameter('ebookpagenameprefix');
if (empty($ebookPageNamePrefix)) $ebookPageNamePrefix = 'Ebook';

$output = '';

// recuperation des pages wikis
$sql = 'SELECT DISTINCT resource FROM '.$this->GetConfigValue('table_prefix').'triples';
$sql .= ' WHERE property="http://outils-reseaux.org/_vocabulary/metadata"
			AND value LIKE "%publication-title%"
			AND resource LIKE "'.$ebookPageNamePrefix.'%" ';
$sql .= ' ORDER BY resource ASC';

$pages = $this->LoadAll($sql);
if (count($pages) > 0) {
	$output .= '<ul class="media-list">'."\n";
	foreach ($pages as $page) {
		$metas = $this->GetMetadatas($page['resource']);
		$output .= '<li class="media">
		<a href="'.$this->href('',$page['resource']).'" class="pull-left">
			<img src="'.$metas['publication-cover-image'].'" alt="cover" class="media-object" width="128" />
		</a>
		<div class="media-body">'."\n";
		if ($this->UserIsAdmin()) $output .= '<a class="btn btn-danger btn-error pull-right" href="'.$this->href('deletepage',$page['resource']).'"><i class="fas fa-trash"></i>&nbsp;'._t('PUBLICATION_DELETE').'</a>';
		$output .= '<h4 class="media-heading"><a href="'.$this->href('',$page['resource']).'">'.$metas['publication-title'].'</a></h4>
			<strong>'.$metas['publication-author'].'</strong><br />'.$metas['publication-description'].'<br /><br />';
		$output .= '<a class="btn btn-info" href="'.$this->href('preview',$page['resource']).'"><i class="fas fa-book-reader"></i>&nbsp;'._t('PUBLICATION_PREVIEW').'</a> <a class="space-left btn btn-primary" href="'.$this->href('pdf',$page['resource']).'"><i class="fas fa-book"></i>&nbsp;'._t('PUBLICATION_DOWNLOAD_PDF').'</a> <!-- pdf download link for '.$page['resource'].' -->
		<br /><br />
		</div>
		</li>'."\n";
	}
	$output .= '</ul>'."\n";
}
else $output .= '<div class="alert alert-info">'._t('PUBLICATION_NO_EBOOK_FOUND').'</div>';

echo $output."\n";
