<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Tests\Repository;

use Eccube\Entity\Master\RoundingType;
use Eccube\Entity\TaxRule;
use Eccube\Tests\EccubeTestCase;
use Eccube\Entity\BaseInfo;
use Eccube\Repository\TaxRuleRepository;
use Eccube\Repository\MemberRepository;
use Eccube\Repository\Master\PrefRepository;
use Eccube\Repository\Master\CountryRepository;

/**
 * TaxRuleRepository test cases.
 *
 * @author Kentaro Ohkouchi
 */
class TaxRuleRepositoryTest extends EccubeTestCase
{
    /**
     * @var \DateTime
     */
    protected $DateTimeNow;

    /**
     * @var \Eccube\Entity\Product
     */
    protected $Product;

    /**
     * @var TaxRule
     */
    protected $TaxRule1;

    /**
     * @var TaxRule
     */
    protected $TaxRule2;

    /**
     * @var TaxRule
     */
    protected $TaxRule3;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var TaxRuleRepository
     */
    protected $taxRuleRepository;

    /**
     * @var MemberRepository
     */
    protected $memberRepository;

    /**
     * @var PrefRepository
     */
    protected $prefRepository;

    /**
     * @var CountryRepository
     */
    protected $countryRepository;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->DateTimeNow = new \DateTime('+1 minutes');

        parent::setUp();

        $this->BaseInfo = $this->entityManager->find(BaseInfo::class, 1);
        $this->taxRuleRepository = $this->container->get(TaxRuleRepository::class);
        $this->memberRepository = $this->container->get(MemberRepository::class);
        $this->prefRepository = $this->container->get(PrefRepository::class);
        $this->countryRepository = $this->container->get(CountryRepository::class);

        $this->BaseInfo->setOptionProductTaxRule(0);
        $this->Product = $this->createProduct('???????????????');
        // 2017-04-01?????????????????????, 2017????????????????????????????????????????????????1??????????????????????????????
        $ApplyDate = new \DateTime('+1 years');
        $this->TaxRule1 = $this->taxRuleRepository->find(1);
        $this->TaxRule1->setApplyDate($this->DateTimeNow);
        $this->TaxRule2 = $this->createTaxRule(10, $ApplyDate);
        $this->TaxRule3 = $this->createTaxRule(8, $ApplyDate);
        $this->entityManager->flush();
    }

    /**
     * Create TaxRule entity
     *
     * @param int $tax_rate
     * @param null $apply_date
     *
     * @return TaxRule
     */
    public function createTaxRule($tax_rate = 8, $apply_date = null)
    {
        $TaxRule = new TaxRule();
        $RoundingType = $this->entityManager->find(RoundingType::class, 1);
        $Member = $this->memberRepository->find(2);
        if (is_null($apply_date)) {
            $apply_date = $this->DateTimeNow;
        }
        $TaxRule->setTaxRate($tax_rate)
            ->setApplyDate($apply_date)
            ->setRoundingType($RoundingType)
            ->setTaxAdjust(0)
            ->setCreator($Member);
        $this->entityManager->persist($TaxRule);
        $this->entityManager->flush();

        return $TaxRule;
    }

    public function testGetList()
    {
        $this->TaxRule2
            ->setProduct($this->Product);
        $this->entityManager->flush();

        // ??????????????????????????????
        $TaxRules = $this->taxRuleRepository->getList();

        $this->expected = 2;
        $this->actual = count($TaxRules);
        $this->verify();
    }

    public function testDelete()
    {
        $this->taxRuleRepository->delete($this->TaxRule2);
        $Results = $this->taxRuleRepository->findAll();

        $this->expected = 2;
        $this->actual = count($Results);
        $this->verify();
    }

    public function testDeleteWithId()
    {
        $this->taxRuleRepository->delete($this->TaxRule2->getId());

        $Results = $this->taxRuleRepository->findAll();

        $this->expected = 2;
        $this->actual = count($Results);
        $this->verify();
    }

    public function testGetByRule()
    {
        // ?????????????????????????????????(???????????????????????????)
        $TaxRule = $this->taxRuleRepository->getByRule();

        $this->expected = 1;
        $this->actual = $TaxRule->getId();
        $this->verify();
    }

    public function testGetByRule2()
    {
        $this->TaxRule1->setApplyDate(new \DateTime('+5 days'));
        $this->TaxRule2->setApplyDate(new \DateTime('-1 days'));
        $this->TaxRule3->setApplyDate(new \DateTime('-2 days'));
        $this->entityManager->flush();

        $this->taxRuleRepository->clearCache();
        $TaxRule = $this->taxRuleRepository->getByRule();

        // TaxRule1 ???????????????, TaxRule2 ??????????????????
        $this->expected = $this->TaxRule2->getId();
        $this->actual = $TaxRule->getId();
        $this->verify();
    }

    public function testGetByRuleWithPref()
    {
        $Pref = $this->prefRepository->find(26);
        $oneDayBefore = new \DateTime('-1 days');

        $this->TaxRule2->setApplyDate($oneDayBefore);
        $this->TaxRule3
            ->setApplyDate($oneDayBefore)
            ->setPref($Pref);
        $this->entityManager->flush();

        $this->taxRuleRepository->clearCache();
        $TaxRule = $this->taxRuleRepository->getByRule(null, null, $Pref, null);

        $this->expected = $this->TaxRule3->getId();
        $this->actual = $TaxRule->getId();
        $this->verify();
    }

    public function testGetByRuleWithCountry()
    {
        $Country = $this->countryRepository->find(300);
        $oneDayBefore = new \DateTime('-1 days');

        $this->TaxRule2->setApplyDate($oneDayBefore)->setCountry($Country);
        $this->TaxRule3->setApplyDate($oneDayBefore);

        $this->entityManager->flush();

        $this->taxRuleRepository->clearCache();
        $TaxRule = $this->taxRuleRepository->getByRule(null, null, null, $Country);

        $this->expected = $this->TaxRule2->getId();
        $this->actual = $TaxRule->getId();
        $this->verify();
    }

    public function testGetByRuleWithProduct()
    {
        $this->BaseInfo->setOptionProductTaxRule(1); // ???????????????ON
        $this->entityManager->flush();
        $oneDayBefore = new \DateTime('-1 days');

        $this->TaxRule2->setApplyDate($oneDayBefore)->setProduct($this->Product);
        $this->TaxRule3->setApplyDate($oneDayBefore);

        $this->entityManager->flush();

        $this->taxRuleRepository->clearCache();
        $TaxRule = $this->taxRuleRepository->getByRule($this->Product, null, null, null);

        $this->expected = $this->TaxRule2->getId();
        $this->actual = $TaxRule->getId();
        $this->verify();
    }

    public function testGetByRuleWithProductClass()
    {
        $this->BaseInfo->setOptionProductTaxRule(1); // ???????????????ON
        $this->entityManager->flush();
        $oneDayBefore = new \DateTime('-1 days');

        $ProductClasses = $this->Product->getProductClasses();
        $ProductClass = $ProductClasses[1];
        $this->TaxRule2->setApplyDate($oneDayBefore)->setProductClass($ProductClass);
        $this->TaxRule3->setApplyDate($oneDayBefore);

        $this->entityManager->flush();

        $this->taxRuleRepository->clearCache();
        $TaxRule = $this->taxRuleRepository->getByRule(null, $ProductClass, null, null);

        $this->expected = $this->TaxRule2->getId();
        $this->actual = $TaxRule->getId();
        $this->verify();
    }

    public function testGetByRuleWithMulti()
    {
        $this->BaseInfo->setOptionProductTaxRule(1); // ???????????????ON
        $this->entityManager->flush();
        $oneDayBefore = new \DateTime('-1 days');

        $Country = $this->countryRepository->find(300);

        // ????????????
        $this->TaxRule2->setApplyDate($oneDayBefore)->setCountry($Country);
        // ???????????????
        $this->TaxRule3->setApplyDate($oneDayBefore)->setProduct($this->Product);

        $this->entityManager->flush();

        $this->taxRuleRepository->clearCache();
        $TaxRule = $this->taxRuleRepository->getByRule($this->Product, null, null, $Country);

        // ????????????????????????????????????
        $this->expected = $this->TaxRule2->getId();
        $this->actual = $TaxRule->getId();
        $this->verify();
    }
}
