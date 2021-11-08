<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\EntityExtension;

trait ProxyInitializationFix
{
    /**
     * Ждем выхода PHP 7.4.6 - https://github.com/symfony/symfony/issues/35574#issuecomment-618446719
     *
     * Api-platform с помощью symfony/serializer/Normalizer/AbstractObjectNormalizer.php пытается десериализовать
     * пустой объект данной сущности, при десерилазиации задействуется Doctrine Proxy object в котором перечислены
     * свойства данного объекта и данные свойства пытаются инициализироваться без использования конструктора данного
     * класса (с помощью самого PHP), но конечно ничего не выходит, потому что мы используем строгий PHP 7.4 и получаем:
     * "Typed property Proxies\\__CG__\\App\\Entity\\Organization::$ must not be accessed before initialization (in __sleep)"
     * Но, если вернуть пустой массив - то ничего инициализировать не нужно, создается просто пустой объект (при этом
     * прокси-объект) и ошибки не возникает.
     *
     * @return array
     */
    // 7.4.6 вышел, по идее данный хак теперь не потребуется: https://www.php.net/ChangeLog-7.php#7.4.6
	// Fixed bug #79447 (Serializing uninitialized typed properties with __sleep should not throw).
    public function __sleep()
    {
        return [];
    }
}
