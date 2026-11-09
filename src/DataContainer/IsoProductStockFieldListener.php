<?php

/*
 * Copyright (c) 2026 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps the back end product form from overwriting stock changes made while the form was open (orders, other editors).
 *
 * The stock value shown in the form is remembered on load; on save only the difference the editor typed is applied
 * to the current database value instead of writing the (possibly stale) absolute value back. After that the typed
 * value is remembered instead, so resubmitting the form (e.g. after another field failed validation) doesn't apply
 * the difference again.
 */
class IsoProductStockFieldListener
{
    public const SESSION_PREFIX = 'huh_iso_stock_loaded.';

    /** Back end actions that render the stock field before saving it; overrideAll and others save without a render. */
    public const FORM_ACTIONS = ['edit', 'editAll'];

    protected Connection   $connection;
    protected RequestStack $requestStack;

    public function __construct(Connection $connection, RequestStack $requestStack)
    {
        $this->connection = $connection;
        $this->requestStack = $requestStack;
    }

    /**
     * load_callback of tl_iso_product.stock.
     *
     * Contao also runs load callbacks in the request that submits the form; the value is only remembered when the
     * form is displayed, otherwise it would already contain the concurrent change.
     */
    public function onLoad($value, DataContainer $dc)
    {
        if (Input::post('FORM_SUBMIT') !== $dc->table && $this->isFormAction() && null !== ($session = $this->getSession())) {
            $session->set(self::SESSION_PREFIX.$dc->id, (string) $value);
        }

        return $value;
    }

    /**
     * save_callback of tl_iso_product.stock.
     */
    public function onSave($value, DataContainer $dc)
    {
        $session = $this->getSession();
        $key = self::SESSION_PREFIX.$dc->id;

        if (null === $session || !$session->has($key)) {
            return $value;
        }

        $loaded = (string) $session->get($key);
        $session->remove($key);

        // e.g. overrideAll sets an absolute value; an entry left over from an abandoned edit form must not turn it into a delta
        if (!$this->isFormAction()) {
            return $value;
        }

        $value = (string) $value;

        if ('' === $value || '' === $loaded) {
            return $value;
        }

        $delta = (int) $value - (int) $loaded;

        if (0 !== $delta) {
            $affected = $this->connection->executeStatement(
                "UPDATE tl_iso_product SET stock = CAST(NULLIF(stock, '') AS SIGNED) + :delta WHERE id = :id AND stock <> ''",
                ['delta' => $delta, 'id' => (int) $dc->id],
                ['delta' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER]
            );

            // the product is no longer stock managed: there is no concurrent stock to preserve, take the typed value
            if (0 === $affected) {
                return $value;
            }
        }

        // the typed value is now applied: if another field fails validation, a resubmit of the same value is delta 0
        // (the next display of the form overwrites the entry, other actions remove it)
        $session->set($key, $value);

        $current = $this->connection->fetchOne('SELECT stock FROM tl_iso_product WHERE id = :id', ['id' => (int) $dc->id], ['id' => ParameterType::INTEGER]);

        return false === $current ? $value : (string) $current;
    }

    protected function isFormAction(): bool
    {
        return \in_array(Input::get('act'), self::FORM_ACTIONS, true);
    }

    protected function getSession()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
