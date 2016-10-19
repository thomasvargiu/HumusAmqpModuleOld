<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace HumusAmqpModuleOldTest\Service;

use HumusAmqpModuleOld\PluginManager\Connection as ConnectionPluginManager;
use HumusAmqpModuleOld\PluginManager\Producer as ProducerPluginManager;
use HumusAmqpModuleOld\Service\ConnectionAbstractServiceFactory;
use Zend\ServiceManager\ServiceManager;

class ProducerAbstractServiceFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ServiceManager
     */
    protected $services;

    /**
     * @var TestAsset\ProducerAbstractServiceFactory
     */
    protected $components;

    public function setUp()
    {
        $config = array(
            'humus_amqp_module' => array(
                'default_connection' => 'default',
                'connections' => array(
                    'default' => array(
                        'host' => 'localhost',
                        'port' => 5672,
                        'login' => 'guest',
                        'password' => 'guest',
                        'vhost' => '/',
                    )
                ),
                'exchanges' => array(
                    'demo-exchange' => array(
                        'name' => 'demo-exchange',
                        'type' => 'direct',
                        'durable' => false,
                        'autoDelete' => true
                    ),
                    'invalid-exchange' => array(
                        'connection' => 'invalid-second'
                    )
                ),
                'queues' => array(
                    'test-queue' => array(
                        'name' => 'test-queue',
                        'exchange' => 'demo-exchange',
                        'autoDelete' => true
                    )
                ),
                'producers' => array(
                    'test-producer' => array(
                        'connection' => 'default',
                        'exchange' => 'demo-exchange',
                        'auto_setup_fabric' => true
                    ),
                    'test-producer-2' => array(
                        'exchange' => 'demo-exchange',
                        'auto_setup_fabric' => true
                    ),
                    'test-producer-3' => array(
                    ),
                    'test-producer-4' => array(
                        'exchange' => 'missing-exchange'
                    ),
                    'test-producer-5' => array(
                        'connection' => 'invalid-connection',
                        'exchange' => 'invalid-exchange'
                    )
                )
            )
        );

        $connection = $this->getMock('AMQPConnection', array(), array(), '', false);
        $channel    = $this->getMock('AMQPChannel', array(), array(), '', false);
        $channel
            ->expects($this->any())
            ->method('getPrefetchCount')
            ->will($this->returnValue(10));
        $exchange      = $this->getMock('AMQPExchange', array(), array(), '', false);
        $exchangeFactory = $this->getMock('HumusAmqpModuleOld\ExchangeFactory');
        $exchangeFactory
            ->expects($this->any())
            ->method('create')
            ->will($this->returnValue($exchange));

        $connectionManager = $this->getMock('HumusAmqpModuleOld\PluginManager\Connection');
        $connectionManager
            ->expects($this->any())
            ->method('get')
            ->with('default')
            ->willReturn($connection);

        $services    = $this->services = new ServiceManager();
        $services->setAllowOverride(true);
        $services->setService('Config', $config);

        $dependentComponent = new ConnectionAbstractServiceFactory();
        $services->setService('HumusAmqpModuleOld\PluginManager\Connection', $cm = new ConnectionPluginManager());
        $cm->addAbstractFactory($dependentComponent);
        $cm->setServiceLocator($services);

        $components = $this->components = new TestAsset\ProducerAbstractServiceFactory();
        $components->setChannelMock($channel);
        $components->setExchangeFactory($exchangeFactory);
        $services->setService('HumusAmqpModuleOld\PluginManager\Producer', $producerManager = new ProducerPluginManager());
        $producerManager->addAbstractFactory($components);
        $producerManager->setServiceLocator($services);
    }

    public function testCreateProducer()
    {
        $producer = $this->components->createServiceWithName($this->services, 'test-producer', 'test-producer');
        $this->assertInstanceOf('HumusAmqpModuleOld\ProducerInterface', $producer);
    }

    public function testCreateProducerWithoutConnectionName()
    {
        $producer = $this->components->createServiceWithName($this->services, 'test-producer-2', 'test-producer-2');
        $this->assertInstanceOf('HumusAmqpModuleOld\ProducerInterface', $producer);
    }

    /**
     * @expectedException \HumusAmqpModuleOld\Exception\InvalidArgumentException
     */
    public function testCreateProducerWithInvalidConnectionName()
    {
        $this->components->createServiceWithName($this->services, 'test-producer-5', 'test-producer-5');
    }

    public function testCreateProducerWithoutExchangeThrowsException()
    {
        $this->setExpectedException(
            'HumusAmqpModuleOld\Exception\InvalidArgumentException',
            'Exchange is missing for producer test-producer-3'
        );
        $this->components->createServiceWithName($this->services, 'test-producer-3', 'test-producer-3');
    }

    public function testCreateProducerWithoutExchangeConfigThrowsException()
    {
        $this->setExpectedException(
            'HumusAmqpModuleOld\Exception\InvalidArgumentException',
            'The producer exchange missing-exchange is missing in the exchanges configuration'
        );
        $this->components->createServiceWithName($this->services, 'test-producer-4', 'test-producer-4');
    }

    public function testCannotCreateProducerWhenConnectionPluginManagerIsMissing()
    {
        $config = array(
            'humus_amqp_module' => array(
                'default_connection' => 'default',
                'connections' => array(
                    'default' => array(
                        'host' => 'localhost',
                        'port' => 5672,
                        'login' => 'guest',
                        'password' => 'guest',
                        'vhost' => '/',
                    )
                ),
                'exchanges' => array(
                    'demo-exchange' => array(
                        'name' => 'demo-exchange',
                        'type' => 'direct',
                        'durable' => false,
                        'autoDelete' => true
                    )
                ),
                'queues' => array(
                    'test-queue' => array(
                        'name' => 'test-queue',
                        'exchange' => 'demo-exchange',
                        'autoDelete' => true
                    )
                ),
                'producers' => array(
                    'test-producer' => array(
                        'connection' => 'default',
                        'exchange' => 'demo-exchange',
                        'auto_setup_fabric' => true
                    ),
                    'test-producer-2' => array(
                        'exchange' => 'demo-exchange',
                        'auto_setup_fabric' => true
                    ),
                )
            )
        );

        $services    = $this->services = new ServiceManager();
        $services->setAllowOverride(true);
        $services->setService('Config', $config);

        $components = $this->components = new TestAsset\ProducerAbstractServiceFactory();
        $services->setService('HumusAmqpModuleOld\PluginManager\Producer', $producerManager = new ProducerPluginManager());
        $producerManager->addAbstractFactory($components);
        $producerManager->setServiceLocator($services);

        try {
            $producerManager->get('test-producer');
        } catch (\Zend\ServiceManager\Exception\ServiceNotCreatedException $e) {
            $p = $e->getPrevious()->getPrevious();
            $this->assertInstanceOf('HumusAmqpModuleOld\Exception\RuntimeException', $p);
            $this->assertEquals('HumusAmqpModuleOld\PluginManager\Connection not found', $p->getMessage());
        }
    }
}
