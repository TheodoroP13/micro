<?php

namespace Psf\Enumerators;

enum DBDriver : int {
    case MySQL          = 1;
    case SQLServer      = 2;
}