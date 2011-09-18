<?php

namespace PHPPdf\Test\Node;

use PHPPdf\Node\Node;
use PHPPdf\Util\DrawingTask;
use PHPPdf\Document;
use PHPPdf\Node\Page;
use PHPPdf\Node\PageContext;
use PHPPdf\Node\DynamicPage;
use PHPPdf\Node\Container;
use PHPPdf\Util\Boundary;

class PageTest extends \PHPPdf\PHPUnit\Framework\TestCase
{
    private $page;
    private $document;

    public function setUp()
    {
        $this->page = new Page();
        $this->document = new Document();
    }

    /**
     * @test
     */
    public function drawing()
    {
        $tasks = $this->page->getOrderedDrawingTasks($this->document);

        foreach($tasks as $task)
        {
            $task->invoke();
        }
    }

    /**
     * @test
     * @expectedException PHPPdf\Exception\DrawingException
     */
    public function failureDrawing()
    {
        $child = $this->getMock('\PHPPdf\Node\Node', array('doDraw'));
        $child->expects($this->any())
              ->method('doDraw')
              ->will($this->throwException(new \Exception('exception')));
         
        $this->page->add($child);
        $tasks = $this->page->getOrderedDrawingTasks($this->document);

        foreach($tasks as $task)
        {
            $task->invoke();
        }
    }

    /**
     * @test
     */
    public function attributes()
    {
        $this->assertEquals(595, $this->page->getWidth());

        $this->page->setPageSize('100:200');
        $this->assertEquals(100, $this->page->getWidth());
        $this->assertEquals(200, $this->page->getHeight());
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function invalidPageSize()
    {
        $this->page->setPageSize('100');
    }

    /**
     * @test
     */
    public function getStartingPoint()
    {
        list($x, $y) = $this->page->getStartDrawingPoint();
        $this->assertEquals($this->page->getHeight(), $y);
        $this->assertEquals(0, $x);
    }

    /**
     * @test
     */
    public function boundary()
    {
        $boundary = $this->page->getBoundary();

        $this->assertEquals(array(0, $this->page->getHeight()), $boundary->getFirstPoint()->toArray());
        $this->assertEquals(array($this->page->getWidth(), 0), $boundary->getDiagonalPoint()->toArray());
    }

    /**
     * @test
     */
    public function innerMargins()
    {
        $height = $this->page->getHeight();
        $width = $this->page->getWidth();

        $firstPoint = $this->page->getFirstPoint();
        $diagonalPoint = $this->page->getDiagonalPoint();

        $verticalMargin = 40;
        $horizontalMargin = 20;
        $this->page->setMargin($verticalMargin, $horizontalMargin);

        $this->assertEquals($height - 2*$verticalMargin, $this->page->getHeight());
        $this->assertEquals($width - 2*$horizontalMargin, $this->page->getWidth());

        $this->assertEquals($firstPoint->translate(20, 40), $this->page->getFirstPoint());
        $this->assertEquals($diagonalPoint->translate(-20, -40), $this->page->getDiagonalPoint());
    }
    
    /**
     * @test
     */
    public function addingFooter()
    {
        $boundary = new Boundary();

        $footerHeight = 25;
        $mock = $this->createFooterOrHeaderMock($boundary, $footerHeight);

        $this->page->setMargin(20);
        $pageBoundary = clone $this->page->getBoundary();
        
        $this->page->setFooter($mock);

        $this->assertEquals($pageBoundary[3]->translate(0, -$footerHeight), $boundary[0]);
        $this->assertEquals($pageBoundary[2]->translate(0, -$footerHeight), $boundary[1]);
        $this->assertEquals($pageBoundary[2], $boundary[2]);
        $this->assertEquals($pageBoundary[3], $boundary[3]);

        $this->assertTrue($this->page->getPlaceholder('footer') === $mock);
    }

    private function createFooterOrHeaderMock(Boundary $boundary, $height = null)
    {
        $mock = $this->getMock('PHPPdf\Node\Container', array('getBoundary', 'getHeight', 'setStaticSize'));
        $mock->expects($this->atLeastOnce())
             ->method('getBoundary')
             ->will($this->returnValue($boundary));

        $mock->expects($this->once())
             ->method('setStaticSize')
             ->with($this->equalTo(true))
             ->will($this->returnValue($mock));

        if($height !== null)
        {
            $mock->expects($this->atLeastOnce())
                 ->method('getHeight')
                 ->will($this->returnValue($height));
        }

        return $mock;
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function exceptionIfFootersHeightIsNull()
    {
        $footer = new Container();

        $this->page->setFooter($footer);
    }
    
    /**
     * @test
     */
    public function addingHeader()
    {
        $boundary = new Boundary();

        $headerHeight = 25;
        $mock = $this->createFooterOrHeaderMock($boundary, 25);
        $this->page->setMargin(20);
        $pageBoundary = clone $this->page->getBoundary();
        $this->page->setHeader($mock);

        $realHeight = $this->page->getRealHeight();
        $this->assertEquals($pageBoundary[0], $boundary[0]);
        $this->assertEquals($pageBoundary[1], $boundary[1]);
        $this->assertEquals($pageBoundary[1]->translate(0, $headerHeight), $boundary[2]);
        $this->assertEquals($pageBoundary[0]->translate(0, $headerHeight), $boundary[3]);

        $this->assertTrue($this->page->getPlaceholder('header') === $mock);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function exceptionIfHeadersHeightIsNull()
    {
        $header = new Container();

        $this->page->setHeader($header);
    }

    /**
     * @test
     * @expectedException \LogicException
     */
    public function exceptionIfPageContextNotFound()
    {
        $this->page->getContext();
    }

    /**
     * @test
     */
    public function gettingContext()
    {
        $dynamicPage = new DynamicPage();
        $context = new PageContext(1, $dynamicPage);
        $this->page->setContext($context);

        $this->assertEquals($context, $this->page->getContext());
    }

    /**
     * @test
     */
    public function copyOfPageCloneAlsoBoundary()
    {
        $copy = $this->page->copy();

        $this->assertTrue($copy->getBoundary() !== null && $this->page->getBoundary() !== null);
        $this->assertFalse($copy->getBoundary() === $this->page->getBoundary());

        $copyBoundary = $copy->getBoundary();
        $boundary = $this->page->getBoundary();
        foreach($copyBoundary as $i => $point)
        {
            $this->assertTrue($point === $boundary[$i]);
        }
    }

    /**
     * @test
     * @dataProvider booleanProvider
     */
    public function drawingTasksFromPlaceholderAreInResultOfGetDrawingTasksIfPrepareTemplateMethodHasNotBeenInvoked($invoke)
    {
        $tasks = array(new DrawingTask(function(){}), new DrawingTask(function(){}));
        
        $header = $this->getMock('PHPPdf\Node\Container', array('format', 'getHeight', 'getOrderedDrawingTasks'));
        $header->expects($this->once())
               ->method('format');
        $header->expects($this->atLeastOnce())
               ->method('getHeight')
               ->will($this->returnValue(10));
        $header->expects($this->once())
               ->method('getOrderedDrawingTasks')
               ->will($this->returnValue($tasks));
        
        $this->page->setHeader($header);
        
        if($invoke)
        {
            $this->page->prepareTemplate($this->document);
        }

        $actualTasks = $this->page->getOrderedDrawingTasks($this->document);
        
        foreach($actualTasks as $task)
        {
            $this->assertEquals(!$invoke, in_array($task, $tasks));
        }
    }
    
    public function booleanProvider()
    {
        return array(
            array(false),
            array(true),
        );
    }

    /**
     * @test
     */
    public function formatMethodDosntInvokePlaceholdersFormatMethod()
    {
        $header = $this->getPlaceholderMockWithNeverFormatMethodInvocation();
        $footer = $this->getPlaceholderMockWithNeverFormatMethodInvocation();
        $watermark = $this->getPlaceholderMockWithNeverFormatMethodInvocation();

        $this->page->setHeader($header);
        $this->page->setHeader($footer);
        $this->page->setWatermark($watermark);

        $this->page->format(new \PHPPdf\Document());
    }

    private function getPlaceholderMockWithNeverFormatMethodInvocation()
    {
        $header = $this->getMock('PHPPdf\Node\Container', array('format', 'getHeight'));
        $header->expects($this->never())
               ->method('format');
        $header->expects($this->any())
               ->method('getHeight')
               ->will($this->returnValue(10));

        return $header;
    }
    
    /**
     * @test
     */
    public function pageCopingDosntCreateGraphicContextIfNotExists()
    {
        $this->assertNull($this->readAttribute($this->page, 'graphicsContext'));
        
        $copyPage = $this->page->copy();
        
        $this->assertNull($this->readAttribute($this->page, 'graphicsContext'));
        $this->assertNull($this->readAttribute($copyPage, 'graphicsContext'));
    }
    
    /**
     * @test
     * @dataProvider pageSizesProvider
     */
    public function resizeBoundaryWhenPageSizeIsSet($width, $height, array $margins)
    {        
        foreach($margins as $name => $value)
        {
            $this->page->setAttribute($name, $value);
        }

        $this->page->setAttribute('page-size', sprintf('%d:%d', $width, $height));
        
        $expectedTopLeftPoint = array($this->page->getMarginLeft(), $height - $this->page->getMarginTop());
        $expectedBottomRightPoint = array($width - $this->page->getMarginRight(), $this->page->getMarginBottom());

        $this->assertEquals($expectedTopLeftPoint, $this->page->getFirstPoint()->toArray());
        $this->assertEquals($expectedBottomRightPoint, $this->page->getDiagonalPoint()->toArray());
    }
    
    public function pageSizesProvider()
    {
        return array(
            array(100, 50, array('margin' => array(0))),
            array(77, 55, array('margin' => array(2, 3, 4, 5))),
        );
    }
    
    /**
     * @test
     */
    public function watermarkShouldBeInTheMiddleOfPage()
    {
        $watermark = new Container();
        $watermark->setHeight(100);
        
        $this->page->setWatermark($watermark);
        
        $this->assertEquals(Node::VERTICAL_ALIGN_MIDDLE, $watermark->getAttribute('vertical-align'));
        $this->assertEquals($this->page->getHeight(), $watermark->getHeight());
        $this->assertEquals($this->page->getWidth(), $watermark->getWidth());
    }
    
    /**
     * @test
     * @dataProvider pageNumberProvider
     */
    public function loadTemplateDocumentWhileFormattingIfExists($numberOfPage)
    {
        $fileOfSourcePage = 'some/file.pdf';
        $width = 100;
        $height = 50;
        $numberOfSourceGcs = 3;
        
        $this->page->setAttribute('document-template', $fileOfSourcePage);
        
        if($numberOfPage !== null)
        {
            $pageContext = $this->getMockBuilder('PHPPdf\Node\PageContext')
                                ->setMethods(array('getPageNumber'))
                                ->disableOriginalConstructor()
                                ->getMock();
                                
            $pageContext->expects($this->once())
                        ->method('getPageNumber')
                        ->will($this->returnValue($numberOfPage));
            $this->page->setContext($pageContext);
        }
        
        $document = $this->getMockBuilder('PHPPdf\Document')
                         ->disableOriginalConstructor()
                         ->setMethods(array('loadEngine'))
                         ->getMock();
                         
        $engine = $this->getMockBuilder('PHPPdf\Engine\Engine')
                       ->getMock();

        $copiedGc = $this->getMockBuilder('PHPPdf\Engine\GraphicsContext')
                         ->getMock();

        $sourceGcs = array();
        for($i=0; $i<$numberOfSourceGcs; $i++)
        {
            $sourceGc = $this->getMockBuilder('PHPPdf\Engine\GraphicsContext')
                             ->getMock();
            $sourceGcs[] = $sourceGc;
        }
                         
        $document->expects($this->once())
                 ->method('loadEngine')
                 ->with($fileOfSourcePage)
                 ->will($this->returnValue($engine));
                 
        $engine->expects($this->once())
               ->method('getAttachedGraphicsContexts')
               ->will($this->returnValue($sourceGcs));
               
        $sourceGcIndex = $numberOfPage === null ? 0 : ($numberOfPage - 1) % $numberOfSourceGcs;

        $sourceGc = $sourceGcs[$sourceGcIndex];

        $sourceGc->expects($this->once())
                 ->method('copy')
                 ->will($this->returnValue($copiedGc));
        
        $copiedGc->expects($this->atLeastOnce())
                 ->method('getWidth')
                 ->will($this->returnValue($width));
        $copiedGc->expects($this->atLeastOnce())
                 ->method('getHeight')
                 ->will($this->returnValue($height));
           
        $this->page->format($document);
        
        $this->assertEquals($width, $this->page->getWidth());
        $this->assertEquals($height, $this->page->getHeight());
    }
    
    public function pageNumberProvider()
    {
        return array(
            array(null),
            array(1),
            array(6),
        );
    }
}