<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\File;

use InvalidArgumentException;
use Ktomk\Pipelines\Runner\Reference;
use Ktomk\Pipelines\TestCase;
use ReflectionException;
use ReflectionObject;

/**
 * @covers \Ktomk\Pipelines\File\File
 */
class FileTest extends TestCase
{
    public function testCreateFromDefaultFile()
    {
        $path = __DIR__ . '/../../../' . File::FILE_NAME;

        $file = File::createFromFile($path);

        $this->assertNotNull($file);

        return $file;
    }

    public function provideWorkingYmlFiles()
    {
        $dir = __DIR__ . '/../../data/yml';

        return array(
            array($dir . '/alias.yml'),
            array($dir . '/alias2.yml'),
            array($dir . '/bitbucket-pipelines.yml'),
            array($dir . '/images.yml'),
            array($dir . '/no-default-pipeline.yml'),
            array($dir . '/steps.yml'),
            array($dir . '/pull-requests-pipeline.yml'),
        );
    }

    /**
     * @dataProvider provideWorkingYmlFiles
     *
     * @param string $path
     */
    public function testCreateFromFile($path)
    {
        $file = File::createFromFile($path);
        $this->assertNotNull($file);
    }

    /**
     */
    public function testCreateFromFileWithError()
    {
        $this->expectException('Ktomk\Pipelines\File\ParseException');
        $this->expectExceptionMessage('/error.yml; verify the file contains valid YAML');

        $path = __DIR__ . '/../../data/yml/error.yml';

        File::createFromFile($path);
    }

    /**
     * @return File
     */
    public function testCreateFromFileWithInvalidId()
    {
        $path = __DIR__ . '/../../data/yml/invalid-pipeline-id.yml';

        $file = File::createFromFile($path);

        $this->assertNotNull($file);

        return $file;
    }

    /**
     * @param File $file
     * @depends testCreateFromFileWithInvalidId
     */
    public function testGetPipelinesWithInvalidIdParseError(File $file)
    {
        $this->expectException('Ktomk\Pipelines\File\ParseException');
        $this->expectExceptionMessage('file parse error: invalid pipeline id \'');

        $file->getPipelines();
    }

    /**
     * @depends testCreateFromDefaultFile
     *
     * @param File $file
     */
    public function testGetImage(File $file)
    {
        $image = $file->getImage();
        $this->assertInstanceOf('Ktomk\Pipelines\File\Image', $image);
        $imageString = (string)$image;
        $this->assertIsString($imageString);
        $expectedImage = File::DEFAULT_IMAGE;
        $this->assertSame($expectedImage, $imageString);
    }

    public function testGetImageSet()
    {
        $expected = 'php:5.6';
        $image = array(
            'image' => $expected,
            'pipelines' => array('tags' => array()),
        );
        $file = new File($image);
        $this->assertSame($expected, (string)$file->getImage());
    }

    public function testMinimalFileStructureAndDefaultValues()
    {
        $minimal = array(
            'pipelines' => array('tags' => array()),
        );

        $file = new File($minimal);

        $this->assertSame(File::DEFAULT_IMAGE, (string)$file->getImage());
        $this->assertSame(File::DEFAULT_CLONE, $file->getClone());

        $steps = $file->getDefault();
        $this->assertNull($steps);
    }

    /**
     */
    public function testMissingPipelineException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Missing required property \'pipelines\'');

        new File(array());
    }

    public function testClone()
    {
        $file = new File(array(
            'clone' => 666,
            'pipelines' => array('default' => array()),
        ));
        $this->assertSame(666, $file->getClone());
    }

    public function testDefaultPipeline()
    {
        $default = array(
            'pipelines' => array(
                'default' => array(
                    array(
                        'step' => array(
                            'script' => array(
                                'echo "hello world"; echo $?',
                            ),
                        ),
                    ),
                ),
            ),
        );

        $file = new File($default);
        $pipeline = $file->getDefault();
        $this->assertInstanceOf('Ktomk\Pipelines\File\Pipeline', $pipeline);
        $steps = $pipeline->getSteps();
        $this->assertArrayHasKey(0, $steps);
        $this->assertInstanceOf('Ktomk\Pipelines\File\Pipeline\Step', $steps[0]);
    }

    /**
     */
    public function testImageNameRequired()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('\'image\' requires a Docker image name');

        new File(
            array(
                'image' => null,
                'pipelines' => array(),
            )
        );
    }

    public function testGetPipelineIds()
    {
        $file = File::createFromFile(__DIR__ . '/../../data/yml/bitbucket-pipelines.yml');
        $ids = $file->getPipelineIds();
        $this->assertIsArray($ids);
        $this->assertArrayHasKey(12, $ids);
        $this->assertSame('custom/unit-tests', $ids[12]);
    }

    /**
     * @depends testCreateFromDefaultFile
     *
     * @param File $file
     */
    public function testGetPipelines(File $file)
    {
        $actual = $file->getPipelines();
        $this->assertGreaterThan(1, count($actual));
        $this->assertContainsOnlyInstancesOf(
            'Ktomk\Pipelines\File\Pipeline',
            $actual
        );
    }

    public function testGetReference()
    {
        $file = File::createFromFile(__DIR__ . '/../../data/yml/bitbucket-pipelines.yml');

        $pipeline = $file->getById('branches/master');
        $this->assertNotNull($pipeline);

        # test instance count
        $default = $file->getById('default');
        $this->assertSame($default, $file->getDefault());
    }

    /**
     * test that in the internal file array, the pipelines
     * data gets referenced to the concrete pipeline object
     * when it once hast been acquired.
     *
     * @throws ReflectionException
     */
    public function testFlyweightPatternWithPatternSection()
    {
        $withBranch = array(
            'pipelines' => array(
                'branches' => array(
                    'master' => array(array('step' => array(
                        'name' => 'master branch',
                        'script' => array('1st line'),
                    )))
                ),
            ),
        );
        $file = new File($withBranch);

        $pipeline = $file->searchTypeReference('branches', 'master');
        $this->asPlFiStName('master branch', $pipeline);

        $refl = new ReflectionObject($file);
        $prop = $refl->getProperty('array');
        $prop->setAccessible(true);
        $array = $prop->getValue($file);
        $actual = $array['pipelines']['branches']['master'];
        $this->assertSame($pipeline, $actual);
    }

    /**
     */
    public function testInvalidReferenceName()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid id \'branch/master\'');

        File::createFromFile(__DIR__ . '/../../data/yml/bitbucket-pipelines.yml')
            ->getById('branch/master'); # must be branch_es_
    }

    /**
     */
    public function testNoSectionException()
    {
        $this->expectException('Ktomk\Pipelines\File\ParseException');
        $this->expectExceptionMessage('section');

        new File(array('pipelines' => array()));
    }

    /**
     */
    public function testNoListInSectionException()
    {
        $this->expectException('Ktomk\Pipelines\File\ParseException');
        $this->expectExceptionMessage('\'default\' requires a list of steps');

        new File(array('pipelines' => array('default' => 1)));
    }

    /**
     */
    public function testNoListInBranchesSectionException()
    {
        $this->expectException('Ktomk\Pipelines\File\ParseException');
        $this->expectExceptionMessage('\'branches\' requires a list');

        new File(array('pipelines' => array('branches' => 1)));
    }

    public function testSearchReference()
    {
        $file = File::createFromFile(__DIR__ . '/../../data/yml/bitbucket-pipelines.yml');

        $pipeline = $file->searchTypeReference('branches', 'master');
        $this->asPlFiStName('master duplicate', $pipeline, 'direct match');

        $pipeline = $file->searchTypeReference('branches', 'my/feature');
        $this->asPlFiStName('*/feature', $pipeline);
    }

    public function testDefaultAsFallBack()
    {
        $withDefault = array(
            'pipelines' => array(
                'default' => array(
                    array('step' => array(
                        'name' => 'default',
                        'script' => array('1st line'),
                    )),
                ),
                'branches' => array(
                    'master' => array(array('step' => array(
                        'name' => 'master branch',
                        'script' => array('1st line'),
                    )))
                ),
            ),
        );
        $file = new File($withDefault);

        $reference = Reference::create('bookmark:xy');
        $pipeline = $file->searchReference($reference);
        $this->asPlFiStName('default', $pipeline);

        $pipeline = $file->searchTypeReference('bookmarks', 'xy');
        $this->asPlFiStName('default', $pipeline);

        $pipeline = $file->searchTypeReference('branches', 'feature/xy');
        $this->asPlFiStName('default', $pipeline);
    }

    public function testNoDefaultAsFallBack()
    {
        $withoutDefault = array(
            'pipelines' => array(
                'branches' => array(
                    'master' => array(array('step' => array(
                        'name' => 'master branch',
                        'script' => array('1st line'),
                    )))
                ),
            ),
        );
        $file = new File($withoutDefault);

        $this->assertNull($file->getIdDefault());
        $this->assertNull($file->getDefault());

        $reference = Reference::create();
        $pipeline = $file->searchReference($reference);
        $this->assertNull($pipeline);

        $reference = Reference::create();
        $pipeline = $file->searchIdByReference($reference);
        $this->assertNull($pipeline);

        $reference = Reference::create('bookmark:xy');
        $pipeline = $file->searchIdByReference($reference);
        $this->assertNull($pipeline);
    }

    /**
     */
    public function testSearchReferenceInvalidScopeException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid type \'invalid\'');

        $file = File::createFromFile(__DIR__ . '/../../data/yml/bitbucket-pipelines.yml');
        $file->searchTypeReference('invalid', '');
    }

    /**
     */
    public function testParseErrorOnGetById()
    {
        $this->expectException('Ktomk\Pipelines\File\ParseException');
        $this->expectExceptionMessage('custom/0: named pipeline required');

        $file = new File(array(
            'pipelines' => array(
                'custom' => array(
                    'void',
                ),
            ),
        ));
        $file->getById('custom/0');
    }

    public function testGetIdFrom()
    {
        $file = new File(array('pipelines' => array('default' => array(
            array('step' => array('script' => array(':')))
        ))));
        $pipeline = $file->getById('default');
        $this->assertNotNull($pipeline);
        $actual = $file->getIdFrom($pipeline);
        $this->assertSame('default', $actual);
    }

    public function testGetIdFromNonFilePipeline()
    {
        $file = new File(array('pipelines' => array('default' => array(
            array('step' => array('script' => array(':')))
        ))));

        $pipeline = new Pipeline($file, array(array('step' => array('script' => array(':')))));
        $this->assertNull($file->getIdFrom($pipeline));
    }

    /**
     */
    public function testInvalidImageName()
    {
        $this->expectException('Ktomk\Pipelines\File\ParseException');
        $this->expectExceptionMessage('invalid Docker image name');

        new File(array(
            'image' => 'php:5.6find . -name .libs -a -type d|xargs rm -rf',
            'pipelines' => array('default' => array()),
        ));
    }

    /**
     */
    public function testValidateImageSectionInvalidName()
    {
        $this->expectException('Ktomk\Pipelines\File\ParseException');
        $this->expectExceptionMessage('\'image\' invalid Docker image name: \'/\'');

        $image = array(
            'image' => array('name' => '/'),
        );
        Image::validate($image);
    }

    public function testValidateImageSectionValidName()
    {
        $image = array(
            'image' => array('name' => 'php/5.6:latest'),
        );
        Image::validate($image);
        $this->addToAssertionCount(1);
    }

    /**
     * assertPipelineFirstStepName
     *
     * @param string $expected
     * @param Pipeline $pipeline
     * @param string $message [optional]
     */
    private function asPlFiStName($expected, Pipeline $pipeline, $message = '')
    {
        $steps = $pipeline->getSteps();
        $first = $steps[0];
        $this->assertSame($expected, $first->getName(), $message);
    }
}
