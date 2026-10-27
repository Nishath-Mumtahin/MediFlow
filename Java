import java.util.ArrayList;
import java.util.Scanner;

class Patient {
    int id;
    String name;
    int age;
    String disease;

    Patient(int id, String name, int age, String disease) {
        this.id = id;
        this.name = name;
        this.age = age;
        this.disease = disease;
    }

    void display() {
        System.out.println(
            id + " | " + name + " | " + age + " | " + disease
        );
    }
}

public class PatientManagement {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        ArrayList<Patient> patients = new ArrayList<>();

        while (true) {
            System.out.println("\n=== MediFlow ===");
            System.out.println("1. Add Patient");
            System.out.println("2. View Patients");
            System.out.println("3. Exit");
            System.out.print("Choose: ");

            int choice = sc.nextInt();
            sc.nextLine();

            if (choice == 1) {
                System.out.print("Patient ID: ");
                int id = sc.nextInt();
                sc.nextLine();

                System.out.print("Name: ");
                String name = sc.nextLine();

                System.out.print("Age: ");
                int age = sc.nextInt();
                sc.nextLine();

                System.out.print("Disease: ");
                String disease = sc.nextLine();

                patients.add(
                    new Patient(id, name, age, disease)
                );

                System.out.println("Patient added successfully!");
            } else if (choice == 2) {
                System.out.println("ID | Name | Age | Disease");

                for (Patient p : patients) {
                    p.display();
                }
            } else if (choice == 3) {
                break;
            } else {
                System.out.println("Invalid choice!");
            }
        }

        sc.close();
    }
}
