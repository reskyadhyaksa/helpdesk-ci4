<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section">
    <div class="container page-zoom">
        <div class="columns is-centered">
            <div class="column is-5-tablet is-4-desktop">
                <h1 class="title">Login</h1>

                <form action="<?= url_to('login.attempt') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="field">
                        <label class="label" for="username">NIK / Username</label>
                        <div class="control">
                            <input class="input" id="username" type="text" name="username" value="<?= esc(old('username'), 'attr') ?>" autocomplete="username" required autofocus>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="password">Kata Sandi</label>
                        <div class="control">
                            <input class="input" id="password" type="password" name="password" autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="field">
                        <button type="submit" class="button is-primary is-fullwidth">Masuk</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
