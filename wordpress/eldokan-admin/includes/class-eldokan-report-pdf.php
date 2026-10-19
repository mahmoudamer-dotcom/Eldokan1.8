<?php
if (!defined('ABSPATH')) exit;
// Shared legacy class; each plugin retains a standalone fallback.
if (class_exists('ElDokan_Report_PDF', false)) return;

/**
 * Tiny dependency-free PDF writer for ElDokan admin reports.
 * Uses built-in PDF Helvetica fonts and transliterates unsupported glyphs.
 */
final class ElDokan_Report_PDF {
    private $pages = [];
    private $page = -1;
    private $y = 806.0;
    private $margin = 36.0;
    private $width = 595.28;
    private $height = 841.89;

    public function __construct() { $this->new_page(); }

    private function clean($text) {
        $text = wp_strip_all_tags((string)$text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        }
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
    }

    private function esc($text) {
        return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $this->clean($text));
    }

    private function new_page() {
        $this->pages[] = '';
        $this->page = count($this->pages)-1;
        $this->y = $this->height - $this->margin;
    }

    private function cmd($s) { $this->pages[$this->page] .= $s."\n"; }

    private function ensure($needed) {
        if ($this->y - $needed < $this->margin) $this->new_page();
    }

    public function line($text, $size=10, $bold=false, $gap=4) {
        $lh = max(12, $size*1.35);
        $this->ensure($lh+$gap);
        $font = $bold ? 'F2' : 'F1';
        $this->cmd("BT /{$font} {$size} Tf {$this->margin} {$this->y} Td (".$this->esc($text).") Tj ET");
        $this->y -= ($lh+$gap);
    }

    public function title($text) { $this->line($text, 18, true, 8); }
    public function heading($text) { $this->line($text, 13, true, 5); }

    public function rule() {
        $this->ensure(10);
        $this->cmd(sprintf('0.75 w %.2f %.2f m %.2f %.2f l S', $this->margin, $this->y, $this->width-$this->margin, $this->y));
        $this->y -= 10;
    }

    private function wrap($text, $width, $size) {
        $text = $this->clean($text);
        $approx = max(5, (int)floor($width / max(3.5, $size*0.52)));
        $words = preg_split('/\s+/', trim($text));
        $lines=[]; $line='';
        foreach ($words as $w) {
            $candidate = $line === '' ? $w : $line.' '.$w;
            if (strlen($candidate) > $approx && $line !== '') { $lines[]=$line; $line=$w; }
            else $line=$candidate;
        }
        if ($line !== '' || !$lines) $lines[]=$line;
        return $lines;
    }

    public function table($headers, $rows, $widths=null, $font=7.5) {
        $count=count($headers); if(!$count) return;
        $usable=$this->width-($this->margin*2);
        if(!$widths){ $widths=array_fill(0,$count,$usable/$count); }
        $sum=array_sum($widths); if($sum<=0)$sum=1;
        $widths=array_map(fn($w)=>$usable*((float)$w/$sum),$widths);
        $drawHeader=function() use($headers,$widths,$font){
            $h=20; $this->ensure($h+4); $x=$this->margin;
            $this->cmd(sprintf('0.92 g %.2f %.2f %.2f %.2f re f 0 g',$x,$this->y-$h,array_sum($widths),$h));
            foreach($headers as $i=>$v){
                $this->cmd(sprintf('0.6 w %.2f %.2f %.2f %.2f re S',$x,$this->y-$h,$widths[$i],$h));
                $this->cmd("BT /F2 {$font} Tf ".($x+3).' '.($this->y-13)." Td (".$this->esc($v).") Tj ET");
                $x+=$widths[$i];
            }
            $this->y-=$h;
        };
        $drawHeader();
        foreach($rows as $row){
            $wrapped=[]; $maxLines=1;
            foreach($headers as $i=>$v){
                $val = is_array($row) ? ($row[$i] ?? '') : '';
                $wrapped[$i]=$this->wrap($val,$widths[$i]-6,$font);
                $maxLines=max($maxLines,count($wrapped[$i]));
            }
            $h=max(18,$maxLines*($font+3)+6);
            if($this->y-$h<$this->margin){ $this->new_page(); $drawHeader(); }
            $x=$this->margin;
            foreach($headers as $i=>$v){
                $this->cmd(sprintf('0.45 w %.2f %.2f %.2f %.2f re S',$x,$this->y-$h,$widths[$i],$h));
                foreach($wrapped[$i] as $li=>$txt){
                    $ty=$this->y-($font+5)-($li*($font+3));
                    $this->cmd("BT /F1 {$font} Tf ".($x+3).' '.$ty." Td (".$this->esc($txt).") Tj ET");
                }
                $x+=$widths[$i];
            }
            $this->y-=$h;
        }
        $this->y-=8;
    }

    public function output($filename='report.pdf') {
        $objects=[];
        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $font1=3; $font2=4;
        $objects[$font1]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$font2]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $pageIds=[]; $next=5;
        foreach($this->pages as $content){
            $pageId=$next++; $contentId=$next++; $pageIds[]=$pageId;
            $objects[$contentId]='<< /Length '.strlen($content)." >>\nstream\n".$content."endstream";
            $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$this->width.' '.$this->height.'] /Resources << /Font << /F1 '.$font1.' 0 R /F2 '.$font2.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
        }
        $kids=implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds));
        $objects[2]='<< /Type /Pages /Kids [ '.$kids.' ] /Count '.count($pageIds).' >>';
        ksort($objects);
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets=[0];
        foreach($objects as $id=>$obj){ $offsets[$id]=strlen($pdf); $pdf.=$id." 0 obj\n".$obj."\nendobj\n"; }
        $xref=strlen($pdf); $max=max(array_keys($objects));
        $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=$max;$i++) $pdf.=sprintf('%010d 00000 n ', $offsets[$i] ?? 0)."\n";
        $pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.sanitize_file_name($filename).'"');
        header('Content-Length: '.strlen($pdf));
        echo $pdf; exit;
    }
}
