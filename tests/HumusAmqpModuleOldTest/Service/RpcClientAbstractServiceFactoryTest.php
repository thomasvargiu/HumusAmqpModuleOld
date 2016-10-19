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
use HumusAmqpModuleOld\PluginManager\RpcClient as RpcClientPluginManager;
use HumusAmqpModuleOld\Service\ConnectionAbstractServiceFactory;
use Zend\ServiceManager\ServiceManager;

class RpcClientAbstractServiceFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ServiceManager
     */
    protected $services;

    /**
     * @var TestAsset\RpcClientAbstractServiceFactory
     */
    protected $components;

    protected function prepare($config)
    {
        $services    = $this->services = new ServiceManager();
        $services->setAllowOverride(true);
        $services->setService('Config', $config);

        $connection = $this->getMock('AMQPConnection', array(), array(), '', false);
        $channel    = $this->getMock('AMQPChannel', array(), array(), '', false);
        $channel
            ->expects($this->any())
            ->method('getPrefetchCount')
            ->will($this->returnValue(10));
        $queue      = $this->getMock('AMQPQueue', array(), array(), '', false);
        $queue
            ->expects($this->any())
            ->method('getChannel')
            ->will($this->returnValue($channel));
        $queueFactory = $this->getMock('HumusAmqpModuleOld\QueueFactory');
        $queueFactory
            ->expects($this->any())
            ->method('create')
            ->will($this->returnValue($queue));

        $connectionManager = $this->getMock('HumusAmqpModuleOld\PluginManager\Connection');
        $connectionManager
            ->expects($this->any())
            ->method('get')
            ->with('default')
            ->willReturn($connection);

        $dependentComponent = new ConnectionAbstractServiceFactory();
        $this->services->setService('HumusAmqpModuleOld\PluginManager\Connection', $cm = new ConnectionPluginManager());
        $cm->addAbstractFactory($dependentComponent);
        $cm->setServiceLocator($this->services);

        $components = $this->components = new TestAsset\RpcClientAbstractServiceFactory();
        $components->setChannelMock($channel);
        $components->setQueueFactory($queueFactory);
        $this->services->setService('HumusAmqpModuleOld\PluginManager\RpcClient', $rpccm = new RpcClientPluginManager());
        $rpccm->addAbstractFactory($components);
        $rpccm->setServiceLocator($this->services);
    }

    public function testCreateRpcClient()
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
                    'test-rpc-client' => array(
                        'name' => 'test-rpc-client',
                        'type' => 'direct'
                    ),
                ),
                'queues' => array(
                    'test-rpc-client' => array(
                        'name' => '',
                        'exchange' => 'test-rpc-client'
                    ),
                ),
                'rpc_clients' => array(
                    'test-rpc-client' => array(
                        'queue' => 'test-rpc-client'
                    )
                )
            )
        );

        $this->prepare($config);

        $rpcClient = $this->components->createServiceWithName($this->services, 'test-rpc-client', 'test-rpc-client');
        $this->assertInstanceOf('HumusAmqpModuleOld\RpcClient', $rpcClient);
    }

    public function testCreateRpcClientWithDefinedConnection()
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
                    'test-rpc-client' => array(
                        'name' => 'test-rpc-client',
                        'type' => 'direct'
                    ),
                ),
                'queues' => array(
                    'test-rpc-client' => array(
                        'name' => '',
                        'exchange' => 'test-rpc-client'
                    ),
                ),
                'rpc_clients' => array(
                    'test-rpc-client' => array(
                        'queue' => 'test-rpc-client',
                        'connection' => 'default'
                    )
                )
            )
        );

        $this->prepare($config);

        $rpcClient = $this->components->createServiceWithName($this->services, 'test-rpc-client', 'test-rpc-client');
        $this->assertInstanceOf('HumusAmqpModuleOld\RpcClient', $rpcClient);
    }

    /**
     * @expectedException HumusAmqpModuleOld\Exception\InvalidArgumentException
     * @expectedExceptionMessage The rpc client queue false-rpc-client-queue-name is missing in the queues configuration
     */
    public function testCreateRpcClientThrowsExceptionOnInvalidQueueName()
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
                    'test-rpc-client' => array(
                        'name' => 'test-rpc-client',
                        'type' => 'direct'
                    ),
                ),
                'queues' => array(
                    'test-rpc-client' => array(
                        'name' => '',
                        'exchange' => 'test-rpc-client'
                    ),
                ),
                'rpc_clients' => array(
                    'test-rpc-client' => array(
                        'queue' => 'false-rpc-client-queue-name'
                    )
                )
            )
        );

        $this->prepare($config);

        $this->components->createServiceWithName($this->services, 'test-rpc-client', 'test-rpc-client');
    }

    /**
     * @expectedException HumusAmqpModuleOld\Exception\InvalidArgumentException
     * @expectedExceptionMessage Queue is missing for rpc client test-rpc-client
     */
    public function testCreateRpcClientThrowsExceptionOnMissingQueue()
    {
        $config = array(
            'humus_amqp_module' => array(
                'default_connection' => 'default',
                'rpc_clients' => array(
                    'test-rpc-client' => array(
                    )
                )
            )
        );

        $this->prepare($config);

        $this->components->createServiceWithName($this->services, 'test-rpc-client', 'test-rpc-client');
    }

    public function testCreateRpcClientThrowsExceptionOnConnectionMismatch()
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
                    'test-rpc-client' => array(
                        'name' => 'test-rpc-client',
                        'type' => 'direct'
                    ),
                ),
                'queues' => array(
                    'test-rpc-client' => array(
                        'name' => '',
                        'exchange' => 'test-rpc-client',
                        'connection' => 'someother'
                    ),
                ),
                'rpc_clients' => array(
                    'test-rpc-client' => array(
                        'queue' => 'test-rpc-client',
                        'connection' => 'default'
                    )
                )
            )
        );

        $this->prepare($config);

        $this->setExpectedException(
            'HumusAmqpModuleOld\Exception\InvalidArgumentException',
            'The rpc client connection for queue test-rpc-client (someother) does not match the rpc client '
            . 'connection for rpc client test-rpc-client (default)'
        );

        $this->components->createServiceWithName($this->services, 'test-rpc-client', 'test-rpc-client');
    }
}
