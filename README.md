# MyCookbook Backend

## Setup
1. Run `composer install`
2. Create the file `.env.local`
3. (In this file set the `APP_ENV` to `prod`)
4. In this file set the `APP_SECRET` to a random string
5. In this file specify the `DATABASE_URL`
6. Generate a public and private key for the JWT authentication:
   
   `openssl genrsa -out config/jwt/private.pem -aes256 4096`
   
   `openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem`

7. In this file set the `JWT_PASSPHRASE` to the passphrase you used while generating the keys.
