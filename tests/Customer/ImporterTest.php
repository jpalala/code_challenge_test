<?php

namespace Customer;

use Mockery;
use TestCase;
use function _\get;
use App\Entities\Customer;
use App\Services\Customer\Manager;
use Illuminate\Support\Collection;
use App\Services\Customer\Importer;
use Doctrine\ORM\Tools\ToolsException;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Events\Dispatcher;
use App\Services\Customer\Models\CustomerImport;
use App\Services\Customer\Contracts\ToImportContract;

class ImporterTest extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        try{
            $this->artisan('doctrine:schema:create');
        }catch(ToolsException $e) {

        }

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('doctrine:schema:drop');
        });
    }

    protected function tearDown(): void
    {
        $this->artisan('doctrine:schema:drop', [
            '--force' => true
        ]);
    }

    public function testCreateCustomersFromEntity()
    {
        $entities = entity(Customer::class, 10)->make();
        $this->assertCount(10, $entities);
    }

    public function testImportedCustomerWithTheSameEmail()
    {
        entity(Customer::class, 10)->make();
        entity(Customer::class)->create([
            'email' => 'email@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);

        $manager = Mockery::mock(Manager::class);
        $manager->shouldReceive('results')->andReturn(new Collection([
            [
                'email' => 'email@example.com',
                'name' => [
                    'first' => 'John',
                    'last' => 'Johnson'
                ],
                'location' => [
                    'country' => 'Philippines',
                    'city' => 'Bacoor'
                ],
                'login' => [
                    'username' => 'testUsername',
                    'md5' => md5('password')
                ],
                'phone' => '(02) 222-2222'
            ]
        ]));

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturnNull();

        $importer = new Importer(
            $manager,
            $this->app->make(EntityManagerInterface::class),
            $dispatcher
        );

        $importerClass = new class implements ToImportContract {
            const MALE = 'male';
            const FEMALE = 'female';
            public function toImport($row, Customer $customer = null) : Customer
            {
                $customer = ($customer ?? new Customer())
                    ->setFirstName(get($row, 'name.first'))
                    ->setLastName(get($row, 'name.last'))
                    ->setUsername(get($row, 'login.username'))
                    ->setGender(get($row, 'gender') == self::MALE ? 0 : 1)
                    ->setCountry(get($row, 'location.country'))
                    ->setCity(get($row, 'location.city'))
                    ->setPhone(get($row, 'phone'))
                    ->setPassword(get($row, 'login.md5'));

                if ($customer !== null) {
                    $customer->setEmail(get($row, 'email'));
                }

                return $customer;
            }
        };

        $importer->import($importerClass);

        $this->seeInDatabase('customers', [
            'email' => 'email@example.com',
            'first_name' => 'John',
            'last_name' => 'Johnson'
        ]);
    }

    public function testImporterClass()
    {
        entity(Customer::class, 10)->make();
        entity(Customer::class)->create([
            'email' => 'email@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);

        $manager = Mockery::mock(Manager::class);
        $manager->shouldReceive('results')->andReturn(new Collection([
            [
                'email' => 'email@example.com',
                'name' => [
                    'first' => 'John',
                    'last' => 'Johnson'
                ],
                'location' => [
                    'country' => 'Philippines',
                    'city' => 'Bacoor'
                ],
                'login' => [
                    'username' => 'testUsername',
                    'md5' => md5('password')
                ],
                'phone' => '(02) 222-2222'
            ]
        ]));

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturnNull();

        $importer = new Importer(
            $manager,
            $this->app->make(EntityManagerInterface::class),
            $dispatcher
        );

        $importer->import(new CustomerImport());

        $this->seeInDatabase('customers', [
            'email' => 'email@example.com',
            'first_name' => 'John',
            'last_name' => 'Johnson',
        ]);
    }
}
