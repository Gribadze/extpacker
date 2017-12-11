
1. Исходные архивы должны быть в директории ./resources/sources
2. Обработанные данные находятся в архиве с тем же названием в директории ./resources/outputs

3. Команда для запуска: "php convert.php", после запуска будут обработаны все новые файлы из директории ./resources/sources

    [root@server converter]# php convert.php
    Converting: source-01                       [  OK  ]
    Converting: source-02                       [  OK  ]

4. Если архив был обработан ранее, то при следующем запуске он будет пропущен (проверяется наличие архива в ./resources/outputs)

    [root@server converter]# php convert.php
    Converting: source-01                       [ SKIP ]
    Converting: source-02                       [ SKIP ]
    Converting: source-03                       [  OK  ]

5. Для запуска должны быть установлены: PHP 5.4+, php-gd, php-curl, php-mbstring, ??. Веб-сервер не нужен.
