<?php

declare(strict_types=1);

namespace CvbankasChart\Tests;

use CvbankasChart\CategoryCatalog;
use CvbankasChart\TitleClassifier;
use PHPUnit\Framework\TestCase;

final class TitleClassifierTest extends TestCase
{
    private TitleClassifier $classifier;

    protected function setUp(): void
    {
        $catalog = new CategoryCatalog(__DIR__.'/../config/categories.json');
        $this->classifier = new TitleClassifier($catalog);
    }

    public function testClassifiesProgrammingLanguages(): void
    {
        $this->assertContains('python', $this->classifier->classify('Senior Python Developer'));
        $this->assertContains('python', $this->classifier->classify('Backend Engineer (FastAPI / Django)'));
        $this->assertContains('javascript', $this->classifier->classify('Senior React / TypeScript Developer'));
        $this->assertContains('javascript', $this->classifier->classify('Node.js Backend Engineer'));
        $this->assertContains('javascript', $this->classifier->classify('Frontend Engineer (VueJS)'));
        $this->assertContains('java', $this->classifier->classify('Senior Java Developer'));
        $this->assertContains('java', $this->classifier->classify('Spring Boot Engineer'));
        $this->assertContains('dotnet', $this->classifier->classify('Senior C# .NET Developer'));
        $this->assertContains('php', $this->classifier->classify('Full-Stack PHP / Laravel Developer'));
        $this->assertContains('go', $this->classifier->classify('Go Software Engineer'));
        $this->assertContains('rust', $this->classifier->classify('Rust Core Developer'));
    }

    public function testClassifiesMobileDevelopers(): void
    {
        $this->assertContains('android', $this->classifier->classify('Android Developer'));
        $this->assertContains('android', $this->classifier->classify('Senior Android Engineer'));
        $this->assertContains('ios', $this->classifier->classify('iOS Developer'));
        $this->assertContains('ios', $this->classifier->classify('Swift / iOS Software Engineer'));
    }

    public function testClassifiesEnterpriseStacks(): void
    {
        $this->assertContains('sap', $this->classifier->classify('Senior SAP Consultant'));
        $this->assertContains('sap', $this->classifier->classify('ABAP Developer'));
        $this->assertContains('salesforce', $this->classifier->classify('Salesforce Developer'));
        $this->assertContains('salesforce', $this->classifier->classify('SFDC Senior Consultant'));
        $this->assertContains('dynamics', $this->classifier->classify('Microsoft Dynamics 365 Specialist'));
        $this->assertContains('dynamics', $this->classifier->classify('Business Central Consultant'));
        $this->assertContains('servicenow', $this->classifier->classify('ServiceNow Platform Developer'));
    }

    public function testClassifiesRoles(): void
    {
        $this->assertContains('pm', $this->classifier->classify('Technical Product Manager'));
        $this->assertContains('pm', $this->classifier->classify('IT Project Manager'));
        $this->assertContains('pm', $this->classifier->classify('Scrum Master / Product Owner'));
        $this->assertContains('qa', $this->classifier->classify('QA Automation Lead'));
        $this->assertContains('qa', $this->classifier->classify('Senior Quality Assurance Tester'));
        $this->assertContains('devops', $this->classifier->classify('Senior DevOps Engineer'));
        $this->assertContains('devops', $this->classifier->classify('Site Reliability Engineer (SRE)'));
        $this->assertContains('ai', $this->classifier->classify('Machine Learning Engineer'));
        $this->assertContains('ai', $this->classifier->classify('AI Specialist'));
        $this->assertContains('ai', $this->classifier->classify('Senior Data Scientist'));
        $this->assertContains('analyst', $this->classifier->classify('Senior Data Analyst'));
        $this->assertContains('analyst', $this->classifier->classify('Business Analyst (IT)'));
        $this->assertContains('analyst', $this->classifier->classify('Information Technology and Communications Analyst'));
        $this->assertContains('analyst', $this->classifier->classify('IT Process Analyst'));
        $this->assertContains('analyst', $this->classifier->classify('Performance Analyst'));
        $this->assertContains('helpdesk', $this->classifier->classify('IT Support Specialist'));
        $this->assertContains('helpdesk', $this->classifier->classify('Service Desk Engineer'));
        $this->assertContains('helpdesk', $this->classifier->classify('IT Specialist'));
        $this->assertContains('helpdesk', $this->classifier->classify('DIGITAL WORKPLACE MAINTENANCE ENGINEER'));
        $this->assertContains('helpdesk', $this->classifier->classify('Mid - Senior Windows Systems Administrator'));
        $this->assertContains('pm', $this->classifier->classify('HEAD OF INFORMATION TECHNOLOGY OPERATIONS DEPARTMENT'));
        $this->assertContains('pm', $this->classifier->classify('IT SYSTEMS DEVELOPMENT MANAGER(S)'));
        $this->assertContains('pm', $this->classifier->classify('Business Developer - HoReCa'));
        $this->assertContains('devops', $this->classifier->classify('IT Controls Specialist'));
        $this->assertContains('security', $this->classifier->classify('DIGITAL SECURITY EXPERT (RENEWABLE ENERGY)'));
        $this->assertContains('security', $this->classifier->classify('Product Cybersecurity Engineer'));
        $this->assertContains('security', $this->classifier->classify('Senior Security Engineer'));
        $this->assertContains('security', $this->classifier->classify('Internal Audit Subject Matter Expert (Technology, DORA and Digital Resilience)'));
        $this->assertContains('hr', $this->classifier->classify('Senior HR Specialist - Crew Operations'));
        $this->assertContains('hr', $this->classifier->classify('HR Projects & Employee Experience Specialist (Baltics)'));
        $this->assertContains('network', $this->classifier->classify('Network Engineer'));
        $this->assertContains('network', $this->classifier->classify('Telecommunication Services Maintenance Specialist'));
        $this->assertContains('network', $this->classifier->classify('IT systems and network administrator(s)'));
    }

    public function testClassifiesDatabaseStack(): void
    {
        $this->assertContains('database', $this->classifier->classify('ORACLE DB PROGRAMMER'));
        $this->assertContains('database', $this->classifier->classify('Senior Data Engineer'));
        $this->assertContains('database', $this->classifier->classify('Database Administrator (PostgreSQL)'));
        $this->assertContains('database', $this->classifier->classify('Senior SQL Developer'));
        $this->assertContains('database', $this->classifier->classify('Firebase / Mobile Backend Engineer'));
    }

    public function testClassifiesKeywordsFromDescription(): void
    {
        // Title has no category keyword, but description contains Python and Docker/DevOps
        $title = 'Senior Software Engineer';
        $description = 'You will be working with Python, Django, PostgreSQL and DevOps infrastructure.';

        $matches = $this->classifier->classify($title, $description);
        $this->assertContains('python', $matches);
        $this->assertContains('devops', $matches);
    }

    public function testCountWithJobDescriptions(): void
    {
        $jobs = [
            ['title' => 'Software Engineer', 'description' => 'Experience with Laravel and PHP is required.'],
            ['title' => 'Backend Developer', 'description' => 'Must know Java and Spring Boot.'],
        ];

        $counts = $this->classifier->count($jobs);
        $this->assertSame(1, $counts['php']);
        $this->assertSame(1, $counts['java']);
        $this->assertSame(0, $counts['python']);
    }
}
